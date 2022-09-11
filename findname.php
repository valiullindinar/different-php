<?php

/*ФУНКЦИИ ДЛЯ ОПРЕДЕЛЕНИЯ СУЩЕСТВОВАНИЯ ФАЙЛА И ЕГО ПЕРЕИМЕНОВАНИЯ*/


function findname($file,$directory,$filename,$is_magic){
    if($is_magic){
		 return md5_file($file);
	 }
	 $ext = strtolower( substr($filename,strrpos($filename,'.')+1) );
	 $filename = substr($filename,0, strrpos($filename,'.')) ;
	 $new_filename =  transliterate_file_name($filename);
	 //Проверка, если файл уже существует
	 if($new_filename==''){
		 return md5_file($file);
	 }
	 //если файл существует, и он такой же, что етсь и сейчас
	 if(is_file($directory.'/'.$new_filename.'.'.$ext) && (md5_file($directory.'/'.$new_filename.'.'.$ext) == md5_file($file)) ){
		return $new_filename;
	 }
	 //если файл существует, и он отличается от того, что есть уже сейчас
	 while(is_file($directory.'/'.$new_filename.'.'.$ext) && (md5_file($directory.'/'.$new_filename.'.'.$ext) != md5_file($file)) ){
		 //файл существует, опредеяем новый
		 // есть тирешки в имени?
		 if(strpos($new_filename,'-')!==false){
			 $lasttire = substr(strrchr($new_filename,'-'),1);
			 //содержит только числа?
			 if(preg_match('#^[0-9]+$#ui',$lasttire)){
				//увеличиваем то, что в конце
				$first_part = substr($new_filename,0,-1 * strlen($lasttire) -1 );
			//	var_dump($new_filename);
				$new_filename = $first_part . '-'. (1*$lasttire + 1);
			//	var_dump($lasttire);
			//	var_dump($first_part);
			//	var_dump($new_filename);
			//	exit;
			 }else{
				 //есть буквы, приписываем число в конец
				 $new_filename = $new_filename . '-1';
			 }
		 }else{
			//тирешек нет - приписываем
			$new_filename = $new_filename . '-1';
		 }
	 }
	 
	 return $new_filename;
 }
 
 
function transliterate_file_name($string)
{
	$converter = array(

		'а' => 'a',   'б' => 'b',   'в' => 'v', 'г' => 'g',   'д' => 'd',   'е' => 'e',
		'ё' => 'e',   'ж' => 'zh',  'з' => 'z',  'и' => 'i',   'й' => 'y',   'к' => 'k',
		'л' => 'l',   'м' => 'm',   'н' => 'n', 'о' => 'o',   'п' => 'p',   'р' => 'r',
		'с' => 's',   'т' => 't',   'у' => 'u',  'ф' => 'f',   'х' => 'h',   'ц' => 'c',
		'ч' => 'ch',  'ш' => 'sh',  'щ' => 'sch',   'ь' => '',  'ы' => 'y',   'ъ' => '',
		'э' => 'e',   'ю' => 'yu',  'я' => 'ya', 'А' => 'A',   'Б' => 'B',   'В' => 'V', 'Г' => 'G',   'Д' => 'D',   'Е' => 'E',
		'Ё' => 'E',   'Ж' => 'Zh',  'З' => 'Z', 'И' => 'I',   'Й' => 'Y',   'К' => 'K',
		'Л' => 'L',   'М' => 'M',   'Н' => 'N',  'О' => 'O',   'П' => 'P',   'Р' => 'R',
		'С' => 'S',   'Т' => 'T',   'У' => 'U',  'Ф' => 'F',   'Х' => 'H',   'Ц' => 'C',
		'Ч' => 'Ch',  'Ш' => 'Sh',  'Щ' => 'Sch', 'Ь' => '',  'Ы' => 'Y',   'Ъ' => '',
		'Э' => 'E',   'Ю' => 'Yu',  'Я' => 'Ya',  ' ' => '-',  ',' => '-',  '.' => '-',  '/' => '-'
	);

	$str =  strtr($string, $converter);
	$str = strtolower($str);

	$str = preg_replace('~[\s\t\_]+~u', '-', $str);
	$str = preg_replace('~-+~u', '-', $str);
	$str = preg_replace('~[^-a-z0-9_]+~u', '', $str);
	$str = trim($str, "-");

	return $str;
    
}