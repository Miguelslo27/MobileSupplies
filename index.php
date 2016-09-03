<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1">

	<title>MobileSupplies.uy</title>

	<!-- Fonts -->
	<link rel="stylesheet" href="/fonts/fonts.css">
	<link rel="stylesheet" href="/fonts/font-awesome.min.css">

	<!-- Styles -->
	<link rel="stylesheet" href="/css/styles.css">
</head>
<body>
	<header>
		<nav>
			<a href="https://facebook.com/mobilesuppliesuy" target="_blank" class="fa fa-facebook"></a>
			<a href="https://www.instagram.com/mobilesuppliesuy/" target="_blank" class="fa fa-instagram"></a>
			<a href="mailto:contacto@mobilesupplies.uy" target="_blank" class="fa fa-envelope-o"></a>
		</nav>
	</header>
	<section>
		<div class="top-shadow"></div>
		<div class="centered">
			<img src="/imgs/logo.png" alt="MobileSupplies.uy" class="logo-max">
			<h3>Próximamente</h3>
			<?php
			if (isset ($_GET['suscribed']) && $_GET['suscribed'] == 'true') {
				?>
				<p class="success">¡Gracias por suscribirte!</p>
				<?php
			} elseif (isset ($_GET['suscribed']) && $_GET['suscribed'] == 'false') {
				if (isset ($_GET['error'])) {
					switch($_GET['error']) {
						case 'LEAD_EXISTS':
						?>
						<p class="error">¡Ya se han suscripto con este correo!</p>
						<?php
						break;
						case 'MALFORMED_EMAIL':
						?>
						<p class="error">¡El correo tiene un formato incorrecto!</p>
						<?php
						break;
					}
				} else {
					?>
					<p class="error">¡Hubo un error interno, inténtalo más tarde!</p>
					<?php
				}
			}
			?>
		</div>
		<span class="fa fa-angle-double-down fa-5x subscribe-signal"></span>
		<div class="bottom-shadow"></div>
	</section>
	<footer>
		<form action="/libs/suscribe.php" id="subscribe" method="post">
			<input type="text" placeholder="Suscribite" name="email" id="email">
			<a href="javascript:subscribe.submit()" class="fa fa-angle-right"></a>
		</form>
	</footer>
</body>
</html>