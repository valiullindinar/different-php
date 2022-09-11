<?

/*
ИСПОЛЬЗОВАНИЕ ФУНКЦИИ ВМЕСТО СТАНДАРТНЫХ БИБЛИОТЕК PHP
С ЗАПИСЬЮ ДАННЫХ В ЛОГИ И ОТПРАВКОЙ ОПОВЕЩЕНИЯ В ТГ

НЕОБХОДИМО УКАЗАТЬ ПОДКЛЮЧЕНИЕ К БД,
УКАЗАТЬ ПУТИ XML ФАЙЛОВ

*/

date_default_timezone_set('Europe/Moscow');

set_time_limit(0);

DB::$settings = array(
	'hostname'	=>	'',
	'username'	=>	'',
	'password'	=>	'',
	'db_name'	=>	''
);

Logger::$settings = array(
	'filename'	=> __DIR__.'/'.basename(__FILE__).'.log.txt',
	'echo'		=> true,
	'save'		=> true,
	'size'		=> 10000,
	'reset'		=> false,
	'errors'	=> array(
		'show'		=> true,
		'log'		=> true,
		'filename'	=> __DIR__.'/'.basename(__FILE__).'.errors.txt'
	)
);
Logger::initialize();

Importer::$settings = array(
	/*УКАЗАТЬ НАИМЕНОВАНИЕ ФАЙЛА, А ТАКЖЕ ПУТЬ ДО НЕГО, НАПРИМЕР:*/
	/*'prices.xml'	=> '/import/prices.xml'*/
);
Importer::update();

class Importer {
	public static $settings;
	public static function update() {
		$started = time();
		$discounts_total = 0;
		$discounts_added = 0;
		Logger::log('Importer started');
		
		
		$xml = file_get_contents(self::$settings['prices.xml']);
		
		/*array_between ПОЛУЧЕНИЕ ОБЪЕКТОВ ИЗ XML В ВИДЕ МАССИВА, УКАЗЫВАЕТСЯ НАЧАЛО И КОНЕЦ*/
		
		$tovar_blocks = self::array_between($xml, '<Предложение', '</Предложение>');
				
		foreach($tovar_blocks as $tovar_block_index => $tovar_block){
			
			
			$product = array(
				/*text_between ПОЛУЧЕНИЕ ЗНАЧЕНИЯ ВНУТРИ ТЕГОВ(НАПРИМЕР)*/
				'id_1c' => (string)self::text_between($tovar_block, '<Ид>', '</Ид>'),
				'updated_at' => date('Y-m-d H:i:s'),
				'skidka' => (string)self::text_between($tovar_block, 'Скидка="', '"'),
				'wholesale' => null,
				'wholesale_show' => null,
				'wholesale_coef' => null,
				'retail' => null,
				'retail_show' => null,
				'retail_coef' => null
			);
			
			$prices_blocks = self::array_between($tovar_block, '<Цена>', '</Цена>');
			
			
			foreach($prices_blocks as $prices_block){
				
				$price_id = self::text_between($prices_block, '<ИдТипаЦены>', '</ИдТипаЦены>');
				/*ОБРАЩЕНИЕ К БД ПО КЛАССУ DB*/
				$type = DB::query("SELECT title FROM prices WHERE id_1c = '{$price_id}' LIMIT 1");
				
				if(mb_strtolower($type[0]['title']) === 'акция'){
					$product['wholesale'] = (string) self::text_between($prices_block, '<ЦенаЗаЕдиницу>', '</ЦенаЗаЕдиницу>');
					$product['wholesale_show'] = (string) self::text_between($prices_block, '<Представление>', '</Представление>');
					$product['wholesale_coef'] = (string) self::text_between($prices_block, '<Коэффициент>', '</Коэффициент>');
				}
				elseif(mb_strtolower($type[0]['title']) === 'розничная'){
					$product['retail'] = (string) self::text_between($prices_block, '<ЦенаЗаЕдиницу>', '</ЦенаЗаЕдиницу>');
					$product['retail_show'] = (string) self::text_between($prices_block, '<Представление>', '</Представление>');
					$product['retail_coef'] = (string) self::text_between($prices_block, '<Коэффициент>', '</Коэффициент>');
				}
				
			}
			
			
			
			$existed_product = DB::query("SELECT id FROM products WHERE id_1c = '{$product['id_1c']}' LIMIT 1");
			if ($existed_product) {
				$product['id'] = $existed_product[0]['id'];
				/*СОХРАНЕНИЕ ЦЕНЫ В БД*/
				DB::insert_item('products', $product, 'UPDATE');
			}
			
		}
		Logger::log('Importer finished, seconds', time() - $started);
		
		/*ПОЛУЧАЕМ ИЗ БД ВСЕ СТРОКИ, КУДА НЕОБХОДИМО ОТПРАВИТЬ ИНФОРМАЦИЮ ОБ ОТПРАВКЕ*/
		$tgrchats = DB::query("SELECT * FROM tgrchats WHERE is_send = 1");
		
		foreach($tgrchats as $tgrchat){
			
			/*НЕОБХОДИМО ОТПРАВИТЬ УКАЗАТЬ ТОКЕН TG*/
			$telegram_token = '';
			$chat_id = $tgrchat['chat_id'];
			
			$timed = time() - $started;
			
			$text = 'Обновление цен завершено. Потраченно секунд: ' . $timed;
			
			
			$ch = curl_init();
			curl_setopt_array(
				$ch,
				array(
					CURLOPT_URL => 'https://api.telegram.org/bot' . $telegram_token . '/sendMessage',
					CURLOPT_POST => TRUE,
					CURLOPT_RETURNTRANSFER => TRUE,
					CURLOPT_TIMEOUT => 10,
					CURLOPT_POSTFIELDS => array(
						'chat_id' => $chat_id,
						'text' => $text
					),
				)
			);
			curl_exec($ch);
		}
		
		
		
		
		
		
		exit;
		
		
		
		
	}
	private static function text_between($text, $start = '', $end = '', $counting = 1) {
		if ($start === '') {
			$starting = 0;
		} else {
			$starting = -1;
			for ($position = 1; $position <= $counting; $position++) {
				$starting = strpos($text, $start, $starting + 1);
				if ($starting === false) {
					break;
				};
			}
		}
		if ($starting === false) {
			return '';
		} else {
			$starting = $starting + strlen($start);
		}
		if ($end === '') {
			$result = substr($text, $starting);
		} else {
			$ending = strpos($text, $end, $starting);
			if ($ending === false) {
				$result = substr($text, $starting);
			} else {
				$result = substr($text, $starting, $ending - $starting);
			}
		}
		return $result;
	}
	private static function array_between($text, $start = '', $end = '') {
		if ($start === '' && $end === '') {
			return array($text);
		}
		if ($start === '') {
			$items = explode($end, $text);
			array_pop($items);
			return $items;
		}
		if ($end === '') {
			$items = explode($start, $text);
			array_shift($items);
			return $items;
		}
		$items = array();
		for ($position = 0; $position <= strlen($text); $position++) {
			$starting = strpos($text, $start, $position);
			if ($starting === false) {
				break;
			} else {
				$starting = $starting + strlen($start);
			}
			$ending = strpos($text, $end, $starting);
			if ($ending === false) {
				break;
			} else {
				array_push($items, substr($text, $starting, $ending - $starting));
				$position = $ending;
			}
		}
		return $items;
	}
}
class DB {
	public static $settings;
	private static $connection;
	private static function connect() {
		if (!self::$connection) {
			self::$connection = mysqli_connect(
				self::$settings['hostname'],
				self::$settings['username'],
				self::$settings['password'],
				self::$settings['db_name']
			);
			mysqli_set_charset(self::$connection, 'utf8mb4');
			mysqli_query(self::$connection, "SET SESSION wait_timeout = 28800");
		}
	}
	public static function query($query) {
		self::connect();
		$mysqli_result = mysqli_query(self::$connection, $query);
		if (is_bool($mysqli_result)) {
			$result = $mysqli_result;
		} else {
			$rows = array();
			while ($row = mysqli_fetch_assoc($mysqli_result)) {
				array_push($rows, $row);
			}
			$result = $rows;
		}
		return $result;
	}
	public static function insert_item($table_name, $item, $method = null) {
		self::connect();
		$sets = array();
		foreach ($item as $key => $value) {
			if (is_null($value)) {
				$item[$key] = $value;
				array_push($sets, "`$key` = NULL");
				
				
			} else {
				$value = mysqli_real_escape_string(self::$connection, $value);
				$item[$key] = $value;
				array_push($sets, "`$key` = '$value'");
			}
		}
		$fields = "`".implode("`, `", array_keys($item))."`";
		$values = "'".implode("', '", array_values($item))."'";
		$sets = implode(', ', $sets);
		switch ($method) {
			case 'IGNORE':
				$query = "INSERT IGNORE INTO `$table_name` ($fields) VALUES ($values)";
				break;
			case 'UPDATE':
				$query = "INSERT INTO `$table_name` ($fields) VALUES ($values) ON DUPLICATE KEY UPDATE $sets";
				break;
			default:
				$query = "INSERT INTO `$table_name` ($fields) VALUES ($values)";
				break;
		}
		
		// return $query;
		
		$result = DB::query($query);
		$id = $result ? mysqli_insert_id(self::$connection) : $result;
		return $id;
	}
}
class Logger {
	public static $settings;
	public static function initialize() {
		if (self::$settings['reset']) {
			if (file_exists(self::$settings['filename'])) {
				unlink(self::$settings['filename']);
			}
		}
		if (self::$settings['size']) {
			register_shutdown_function(function() {
				if (file_exists(self::$settings['filename'])) {
					$file = file(self::$settings['filename']);
					if (count($file) > self::$settings['size']) {
						$content = array_slice($file, count($file) - self::$settings['size'], self::$settings['size']);
						file_put_contents(self::$settings['filename'], $content);
					}
				}
			});
		}
		if (self::$settings['errors']['show']) {
			ini_set('display_errors', true);
			ini_set('display_startup_errors', true);
		} else {
			error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
			ini_set('display_errors', false);
			ini_set('display_startup_errors', false);
		}
		if (self::$settings['errors']['log']) {
			error_reporting(E_ALL);
			ini_set('log_errors', true);
			ini_set('error_log', self::$settings['errors']['filename']);
		} else {
			ini_set('log_errors', false);
		}
	}
	public static function log() {
		$line = date('d.m.Y'."\t".'H:i:s')."\t".implode("\t", func_get_args())."\n";
		if (self::$settings['echo']) {
			echo $line;
		}
		if (self::$settings['save']) {
			file_put_contents(self::$settings['filename'], $line, FILE_APPEND);
		}
	}
}