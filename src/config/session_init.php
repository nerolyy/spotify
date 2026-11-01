<?php
/**
 * Единые параметры cookie сессии для всего приложения под /muzic2/
 * (иначе PHP по умолчанию может привязать cookie к /muzic2/src/api/ и сессия
 * ведёт себя непредсказуемо при запросах с /muzic2/public/ и обратно).
 */
if (session_status() === PHP_SESSION_NONE) {
	$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
	session_set_cookie_params([
		'lifetime' => 0,
		'path' => '/muzic2/',
		'domain' => '',
		'secure' => $secure,
		'httponly' => true,
		'samesite' => 'Lax',
	]);
	session_start();
}
