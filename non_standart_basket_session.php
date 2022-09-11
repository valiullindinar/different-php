<?php

/*
	ФУНКЦИИ ДЛЯ РАБОТЫ С НЕСТАНДАРТНЫМИ КОРЗИНАМИ (В ДАННОМ СЛУЧАЕ СБОР ЛАНЧБОКСА). СРАВНЕНИЕ МАССИВОВ В СЕССИИ
*/

function add ($params) {
	/*ФУНКЦИЯ ДОБАВЛЕНИЯ В КОРЗИНУ*/
	$product_exists = false;
	foreach ($_SESSION['basket'] as $key => $value) {
		if(isset($value['lunch_id']) && $value['lunch_id'] != ''){
			if ($value['lunch_id'] == $params['lunch_id'] && equil_mass($value['lunch_product_id'], $params['lunch_product_id']) && $value['bread_count'] == $params['bread_count'] && $value['is_tea'] == $params['is_tea']){
				$product_exists = true;	
				$product_id = $key;
				/*ПРИ НАХОЖДЕНИИ ТАКОГО ЖЕ ЛАНЧА В СЕССИИ КОРЗИНЫ, ПРЕРЫВАЕМ ЦИКЛ*/
				break;
			}
		
		}
	}
	if ($product_exists) {
		if ($params['in_basket'] > 0) {
			$_SESSION['basket'][$product_id]['in_basket'] = $params['in_basket'];
		} else {
			unset ($_SESSION['basket'][$product_id]);
		}
	} else {
		if ($params['in_basket'] > 0) {
			$_SESSION['basket'][] = $params;
		}
	}
}

function equil_mass($mass1, $mass2){
	/*ФУНКЦИЯ ДЛЯ СРАВНЕНИЯ ДВУХ МАССИВОВ*/
	if(count($mass1) != count($mass2)){
		return false;
	}
	else{
		$result = array_diff_assoc($mass1, $mass2);
		if(count($result) == 0){
			return true;
		}
		else{
			return false;
		}
	}
}