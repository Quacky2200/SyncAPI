<?php
class Redirect extends Page {

	public function getName() {
		return __CLASS__;
	}

	public function getURL() {
		return "/";
	}

	public function isMatch($URL) {
		return true;
	}

	public function run($template) {}

	public function show($template) {
		$url = "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
		$host = 'https://host.example.com';
		header("Location: ${host}" . parse_url($url, PHP_URL_PATH));
	}
}
?>
