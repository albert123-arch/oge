<?php
require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
	$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
	$mysqli->set_charset('utf8mb4');
} catch (Throwable $exception) {
	error_log('Database connection failed: ' . $exception->getMessage());
	http_response_code(500);
	?>
	<!DOCTYPE html>
	<html lang="ru">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Сервис недоступен</title>
		<style>
			body {
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
				display: flex;
				align-items: center;
				justify-content: center;
				min-height: 100vh;
				margin: 0;
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			}
			.container {
				background: white;
				border-radius: 8px;
				box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
				padding: 40px;
				max-width: 500px;
				text-align: center;
			}
			h1 {
				color: #333;
				margin-top: 0;
				font-size: 28px;
			}
			p {
				color: #666;
				line-height: 1.6;
				margin: 15px 0;
			}
			.error-code {
				display: inline-block;
				background: #f0f0f0;
				padding: 5px 10px;
				border-radius: 4px;
				font-family: monospace;
				color: #d32f2f;
				margin: 10px 0;
			}
			a {
				display: inline-block;
				margin-top: 20px;
				padding: 10px 20px;
				background: #667eea;
				color: white;
				text-decoration: none;
				border-radius: 4px;
				transition: background 0.3s;
			}
			a:hover {
				background: #764ba2;
			}
		</style>
	</head>
	<body>
		<div class="container">
			<h1>⚠️ Сервис недоступен</h1>
			<p>К сожалению, в данный момент мы не можем подключиться к базе данных.</p>
			<p>Наша команда уже уведомлена о проблеме и работает над её решением.</p>
			<div class="error-code">Ошибка 500</div>
			<a href="/">Вернуться на главную</a>
		</div>
	</body>
	</html>
	<?php
	exit;
}