<?php

/*
ДАННОЙ ФУНКЦИЕЙ АВТОМАТИЗИРОВАЛ ПОДСЧЕТ СУММЫ ПРОДАННЫХ ТОВАРОВ В МАГАЗИНЕ (ПО ПРИКРЕПЛЕННОМУ ФАЙЛУ, КОТОРЫЙ ПРИХОДИТ ИЗ БАНКА В excel формате на яндекс почту) И НАСТРОИЛ ОТПРАВКУ В ТГ.
НЕОБХОДИМО ПОДКЛЮЧИТЬ БИБЛИОТЕКУ PHP EXCEL
*/

function parse_xls()
{
	header("Content-Type: text/html; charset=utf-8");
	/*
	необходимо указать логин и пароль (для приложений), где нужно проверять, а также почту с какой именно почты приходит
	*/
	$username = '';
	$password = '';
	$from_email = '';
	$imap = imap_open("{imap.yandex.ru:993/imap/ssl}INBOX", "{$username}", "{$password}");

	$yesterday = date('d F Y 20:00:00',strtotime("-1 days"));
	
	
	
	$mails_id = imap_search($imap, 'FROM "' . $from_email . '" SINCE "' . $yesterday . '"');
		
	foreach ($mails_id as $num) {
		
		$structure = imap_fetchstructure($imap, $num);
		if (isset($structure->parts[1])) {
			$part = $structure->parts[1];
			$message = imap_fetchbody($imap,$num,1);
			if(strpos($message,"<html") !== false) {
				$message = trim(utf8_encode(quoted_printable_decode($message)));
			}
			else if ($part->encoding == 3) {
				$message = imap_base64($message);
			}
			else if($part->encoding == 2) {
				$message = imap_binary($message);
			}
			else if($part->encoding == 1) {
				$message = imap_8bit($message);
			}
			else {
				$message = trim(utf8_encode(quoted_printable_decode(imap_qprint($message))));
			}
		}
		/*МАССИВ ДЛЯ СОХРАНЕНИЯ ПРИКРЕПЛЕННЫХ ФАЙЛОВ*/
		$attachments = array();
		if(isset($structure->parts) && count($structure->parts)) {

			for($k = 0; $k < count($structure->parts); $k++) {

				$attachments[$k] = array(
					'is_attachment' => false,
					'filename' => '',
					'name' => '',
					'attachment' => ''
				);
				
				if($structure->parts[$k]->ifdparameters) {
					foreach($structure->parts[$k]->dparameters as $object) {
						if(strtolower($object->attribute) == 'filename') {
							$attachments[$k]['is_attachment'] = true;
							$attachments[$k]['filename'] = $object->value;
						}
					}
				}
				
				if($structure->parts[$k]->ifparameters) {
					foreach($structure->parts[$k]->parameters as $object) {
						if(strtolower($object->attribute) == 'name') {
							$attachments[$k]['is_attachment'] = true;
							$attachments[$k]['name'] = $object->value;
						}
					}
				}
				
				if($attachments[$k]['is_attachment']) {
					$attachments[$k]['attachment'] = imap_fetchbody($imap, $num, $k+1);
					
					
					
					if($structure->parts[$k]->encoding == 3) { // 3 = BASE64
						$attachments[$k]['attachment'] = base64_decode($attachments[$k]['attachment']);
						
						$filename = $_SERVER['DOCUMENT_ROOT'] . '/file' . $k . '.xls';
					
						$attachments[$k]['url'] = $filename;
						/*СОХРАНЯЕМ ФАЙЛ*/
						file_put_contents($filename, $attachments[$k]['attachment']);
					}
					elseif($structure->parts[$k]->encoding == 4) { // 4 = QUOTED-PRINTABLE
						$attachments[$i]['attachment'] = quoted_printable_decode($attachments[$k]['attachment']);
						
						
					}
				}
			}
		}
		
		
		
		/*ПРОПУСКАЮ ЦИКЛ, ТАК КАК ВСЕГДА 1 ТОЛЬКО ПИСЬМО ПРИХОДИТ*/
		continue;
		
	}
		
	imap_close($imap);

		
	$elems = [];
				
	$arr = [];
	/*ПЕРЕМЕННАЯ ДЛЯ ТЕКСТ, КОТОРЫЙ БУДЕТ ОТПРАВЛЯТЬСЯ В ТГ*/
	$text = '';
	
	foreach($attachments as $attachment){
		
		if($attachment['url'] == ''){
			continue;
		}

		if(!file_exists($attachment['url'])){
			continue;
		}
		/*ДЛЯ РАБОТЫ С EXCEL НЕОБХОДИМО ПОДКЛЮЧИТЬ PHPExcel*/
		$objPHPExcel = PHPExcel_IOFactory::load($attachment['url']);
		$sheetData = [];
		foreach ($objPHPExcel->getAllSheets() as $sheet) {
			
			$sheetData[$sheet->getTitle()] = $sheet->toArray(null, true, true, true);
			
			$row = $sheet->toArray(null, true, true, true);
			
			$id = (string) $row[10]['C'];
			
			
			if(in_array($id, $elems) || $id == ''){
				continue;
			}
			else{
				$elems[] = $id;
				$arr['id'] = $id;
			}
			
			$text .= 'Terminal ID: ' . $id . '; ';
			$dates_arr = array();
			
			$sum = 0;
			
			$date_text = '';
			
			foreach ($row as $kd => $vd) {
				if ($kd <= 13) {
					continue;
				}
				
				if($row[$kd]['A'] != $id){
					continue;
				}
				
				$date = $row[$kd]['F'];
				$pervshast = explode(' ', $date)[0];
				
				
				
				if($pervshast != date('Y-m-d',strtotime("-1 days"))){
					continue;
				}
				
				if(!in_array($pervshast, $arr['date'])){
					$arr['date'][] = $pervshast;
					
					$date_text .= 'Date: ' . implode(' ', $pervshast);
				}
				$prodano = str_replace(" ", "", $row[$kd]['C']);
				$prodano = str_replace(",", "", $prodano);
				/*ПОЛУЧАЕМ СУММУ ЗА КАЖДЫЙ ДЕНЬ*/
				$sum += (float) $prodano;
			}
			$text .= $date_text . '; ';
			$arr['sum'] = $sum;
			$text .= 'Summ: ' . $sum . ' руб. ;;; ';

			unlink($attachment['url']);
		}


	}

	
	
	/*НЕОБХОДИМО УКАЗАТЬ ТОКЕН И CHAT ID TG*/
	$telegram_token = '';
	$chat_id = '';	
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
	
	
	

	exit;
}