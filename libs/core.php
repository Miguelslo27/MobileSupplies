<?php
require_once ('config.php');
require_once ('constants.php');
require_once ('helpers.php');
require_once ('headers.php');

require_once ('phpdb/MysqliDb.php');
require_once ('mailer/PHPMailerAutoload.php');

// connect to db
global $config;
$db = new MysqliDb($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);

function getDBConnection() {
	global $db;
	return $db;
}

// Re formular función
function saveUser() {
	global $siteSettings;
	$user = loadUser();

	debugging('Actual User ($user):', $user);

	if($user['user'] && !isset($_POST['id'])) {
		$user['status'] = 'LOGGED';
		return $user;
	}

	$registerStatus = array(
		'status' => 'OK',
		'fields_messages' => array()
	);

	if(isset($_POST['guardar'])) {
		$email        = @trim ($_POST['email']) OR NULL;
		$email        = @str_replace (" ", "", strtolower ($email)) OR NULL;

		$registerStatus['status']                   = 'DUPLICATE_EMAIL';
		$registerStatus['fields_messages']['email'] = 'DUPLICATE_EMAIL';

		if (!isset ($_POST['id']) && checkCurrentUser($email)) {
			return array ('user' => NULL, 'cart' => NULL, 'status' => 'DUPLICATE_EMAIL', 'register_status' => $registerStatus);
		}

		$nombre       = @trim ($_POST['nombre']) OR NULL;
		$apellido     = @trim ($_POST['apellido']) OR NULL;
		$direccion    = @trim ($_POST['direccion']) OR NULL;
		$rut          = @trim ($_POST['rut']) OR NULL;
		$celular      = @trim ($_POST['celular']) OR NULL;
		$telefono     = @trim ($_POST['telefono']) OR NULL;
		$departamento = @trim ($_POST['departamento']) OR NULL;
		$ciudad       = @trim ($_POST['ciudad']) OR NULL;
		$email2       = @trim ($_POST['email2']) OR NULL;
		$email2       = @str_replace (" ", "", strtolower ($email2)) OR NULL;
		$pass         = @trim ($_POST['pass']) OR NULL;
		$pass2        = @trim ($_POST['pass2']) OR NULL;

		if ($nombre == '') {
			$registerStatus['status']                    = 'ERROR';
			$registerStatus['fields_messages']['nombre'] = 'REQUIRED';
		}
		if ($apellido == '') {
			$registerStatus['status']                      = 'ERROR';
			$registerStatus['fields_messages']['apellido'] = 'REQUIRED';
		}
		if ($celular == '') {
			$registerStatus['status']                     = 'ERROR';
			$registerStatus['fields_messages']['celular'] = 'REQUIRED';
		}
		if ($email == '') {
			$registerStatus['status']                   = 'ERROR';
			$registerStatus['fields_messages']['email'] = 'REQUIRED';
		}
		if ($email2 == '') {
			$registerStatus['status']                    = 'ERROR';
			$registerStatus['fields_messages']['email2'] = 'REQUIRED';
		}
		if ($pass == '') {
			$registerStatus['status']                  = 'ERROR';
			$registerStatus['fields_messages']['pass'] = 'REQUIRED';
		}
		if ($pass2 == '') {
			$registerStatus['status']                   = 'ERROR';
			$registerStatus['fields_messages']['pass2'] = 'REQUIRED';
		}

		// Check email wellformed
		if (!preg_match ('/^[a-z0-9]+[a-z0-9_.-]+@[a-z0-9_.-]+$/', $email)) {
			$registerStatus['status']                   = 'ERROR';
			$registerStatus['fields_messages']['email'] = 'EMAIL_MALFORMED';
		}

		// Check for repeated emails
		if ($email != $email2) {
			$registerStatus['status']                    = 'ERROR';
			$registerStatus['fields_messages']['email2'] = 'INCORRECT_EMAIL';
		}

		// Check for repeated passwords
		if ($pass != $pass2) {
			$registerStatus['status']                    = 'ERROR';
			$registerStatus['fields_messages']['pass2'] = 'INCORRECT_PASS';
		}
	} else {
		return array ('user' => NULL, 'cart' => NULL,  'status' => 'READY_TO_LOGIN');
	}

	if ($registerStatus['status'] == 'ERROR') {
		return array ('user' => NULL, 'cart' => NULL,  'status' => 'MISSING_REQUIRED_FIELDS', 'register_status' => $registerStatus);
	}

	$db = $GLOBALS['db'];

	if (!isset ($_POST['id'])) {
		$sql = 'INSERT INTO `usuario` (`nombre`, `apellido`, `rut`, `email`, `password`, `codigo`, `direccion`, `telefono`, `celular`, `departamento`, `ciudad`, `administrador`) VALUES ("'.$nombre.'","'.$apellido.'","'.$rut.'","'.$email.'","'.md5($pass.$email).'","'.md5($email).'","'.$direccion.'","'.$telefono.'","'.$celular.'","'.$departamento.'","'.$ciudad.'",0)';
	} else {
		$sql = 'UPDATE `usuario` SET ';

		if($nombre != "") {
			$sql .= '`nombre` = "'.ucfirst (strtolower ($nombre)).'",';
		}
		if($apellido != "") {
			$sql .= '`apellido` = "'.ucfirst (strtolower ($apellido)).'",';
		}
		if($rut != "") {
			$sql .= '`rut` = "'.$rut.'",';
		}
		if($email != "") {
			$sql .= '`email` = "'.$email.'",';
		}
		if($pass != "") {
			$sql .= '`password` = "'.md5 ($pass.$email).'",';
		}
		if($direccion != "") {
			$sql .= '`direccion` = "'.$direccion.'",';
		}
		if($telefono != "") {
			$sql .= '`telefono` = "'.$telefono.'",';
		}
		if($celular != "") {
			$sql .= '`celular` = "'.$celular.'",';
		}
		if($departamento != "") {
			$sql .= '`departamento` = "'.ucfirst (strtolower ($departamento)).'",';
		}
		if($ciudad != "") {
			$sql .= '`ciudad` = "'.ucfirst (strtolower ($ciudad)).'",';
		}

		$sql  = substr ($sql, 0, -1);
		$sql .= ' WHERE `id` = '.$_POST['id'];
	}

	$cid = $db->insert($sql);
	debugging('New User ($cid):', $cid);

	if(isset ($_POST['id'])) {
		$res = loginUser($email, $pass, true);
		$res['register_status'] = $registerStatus;

		return $res;
	} elseif ($cid != 0) {
		// TODO - enviar email con código de activación

		debugging('Site Settings ($siteSettings):', $siteSettings);
		debugging('Send notification from messages $siteSettings[notificaciones][nuevo-mensaje]:', $admin_notifications);
		debugging('Send notification from messages $siteSettings[notificaciones][nuevo-mensaje]:', $admin_notifications['nuevo-usuario']);

		$admin_notifications = (array) json_decode ($siteSettings['notificaciones']);

		if ($admin_notifications['nuevo-mensaje'] == 'on') {
			$mail = array (
				'address'      => $siteSettings['admin-email'],
				'address_name' => 'Administrador'.' ('.$siteSettings['dominio'].')',
				'from'         => array(
					'email'   => 'unifit@unifit.com.uy',
					'name'    => 'Unifit',
					'contact' => 'Registro'
				),
				'subject'      => 'Nuevo usuario registrado',
				'content'      => 'Se ha registrado un nuevo usuario en '.$siteSettings['dominio'].'.'
			);

			$sent = sentEmail($mail);
		}
		
		// Guardo el registro en sesion para verificar la bienvenida
		$_SESSION['registered'] = true;	
		// Redirecciono a página de bienvenida
		header ('Location: /registro/bienvenida/'.($_SERVER['QUERY_STRING'] != '' ? '?'.$_SERVER['QUERY_STRING'] : ''));
	}

	return array ('user' => NULL, 'cart' => NULL,  'status' => 'ERROR_SAVING');

}

function loadUsers() {
	// get config
	$config = $GLOBALS['config'];
	// connect to db
	$db     = new MysqliDb($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
	// Get useres
	$users  = $db->get('usuario');
	
	return $users;
}

function subscribe($email) {
	// Compruebo el email que se está suscribiendo
	$email  = validateEmail($email);

	if($email) {
		// Chequeo la existencia del email que se está suscribiendo
		$db = getDBConnection();
		$db->where("email", $email);
		$lead = $db->getOne("suscripcion");

		if(!$lead) {
			// Chequeo la existencia de usuario con el email
			$db->where("email", $email);
			$user   = $db->getOne("usuario");
			$userId = null;

			if($user) {
				$user['suscrito'] = 1;
				$userId = $user['id'];
				$db->where('id', $userId);
				$db->update('usuario', $user);
			}
			// Si no hay usuario con el email, creo uno potencial
			else {
				$user = array(
					'email' => $email,
					'suscrito' => 1,
					'registrado' => 0
				);
				$newUserId = $db->insert('usuario', $user);
				$userId    = $newUserId;
			}

			// agrego el suscriptor
			$lead = array(
				'email'          => $email,
				'idUsuario'      => $userId,
				'noticias'       => 1,
				'notificaciones' => 1,
				'catalogos'      => 1
			);

			$newLeadId = $db->insert('suscripcion', $lead);
			redirectTo('/', array('suscribed' => 'true'));
		} else {
			redirectTo('/', array('suscribed' => 'false', 'error' => 'LEAD_EXISTS'));
		}
	}
	// Para email incorrecto retorno con error
	else {
		redirectTo('/', array('suscribed' => 'false', 'error' => 'MALFORMED_EMAIL'));
	}
}

function loadSales() {
	// get config
	$config = $GLOBALS['config'];
	// connect to db
	$db     = new MysqliDb($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
	// Get sales
	$db->join('usuario u', 'p.usuario_id = u.id', 'INNER');
	$db->where('u.administrador != 1');
	$sales = $db->get('pedido p', null, array (
		'p.id pedido_id',
		'p.fecha pedido_fecha',
		'p.cantidad pedido_cantidad',
		'p.total pedido_total',
		'p.retira pedido_retira',
		'p.compra_en_local pedido_compra_en_local',
		'p.direccion_de_entrega pedido_direccion_de_entrega',
		'p.forma_de_pago pedido_forma_de_pago',
		'p.lugar pedido_lugar',
		'p.estado pedido_estado',
		'u.nombre usuario_nombre',
		'u.apellido usuario_apellido',
		'u.rut usuario_rut',
		'u.email usuario_email',
		'u.telefono usuario_telefono',
		'u.celular usuario_celular'
	));

	return $sales;
}

function loadSale($sid) {
	// get config
	$config = $GLOBALS['config'];
	// connect to db
	$db     = new MysqliDb($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
	// Get sales
	$db->join('usuario u', 'p.usuario_id = u.id', 'INNER');
	$db->where('u.administrador != 1');
	$db->where('p.id = '.$sid);

	$sale = $db->get('pedido p', null, array (
		'p.id pedido_id',
		'p.fecha pedido_fecha',
		'p.cantidad pedido_cantidad',
		'p.total pedido_total',
		'p.retira pedido_retira',
		'p.compra_en_local pedido_compra_en_local',
		'p.direccion_de_entrega pedido_direccion_de_entrega',
		'p.forma_de_pago pedido_forma_de_pago',
		'p.lugar pedido_lugar',
		'p.estado pedido_estado',
		'u.nombre usuario_nombre',
		'u.apellido usuario_apellido',
		'u.rut usuario_rut',
		'u.email usuario_email',
		'u.telefono usuario_telefono',
		'u.celular usuario_celular'
	));

	$db->join('articulo a', 'ap.articulo_id = a.id', 'INNER');
	$db->where('ap.pedido_id', $sid);

	$sale_articles = $db->get('articulo_pedido ap', null, array(
		'ap.talle talle_pedido',
		'ap.color color_pedido',
		'ap.precio_actual precio_pedido',
		'ap.cantidad cantidad_pedido',
		'ap.subtotal subtotal_pedido',
		'a.nombre nombre',
		'a.codigo codigo',
		'a.imagenes_url imagenes_url'
	));

	$sale[0]['articulos'] = $sale_articles;

	return $sale[0];
}

/*
 * $cid  category id
 * 
 */
function loadCategories($cid = null, $statusIds = null, $children = false) {
	// get config
	$config = $GLOBALS['config'];
	// connect to db
	$db     = new MysqliDb($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
	// if cid is not empty, set the where clause
	if(isset($cid)) {
		$db->where('categoria_id', $cid);
	}

	$statusWhere = Array();
	for($s = 0; $s < count($statusIds); $s++) {
		$statusWhere[] = 'estado = ?';
	}

	$statusWhere = '('.implode(' or ', $statusWhere).')';

	// Where clause for status
	$db->where($statusWhere, $statusIds);

	// get categories
	$cats = $db->get('categoria');

	if($children) {
		for($cat = 0; $cat < count($cats); $cat++) {
			$cats[$cat]['subcategorias'] = loadCategories($cats[$cat]['id'], array(5));
		}
	}

	// return categories
	return $cats;
}

function loadCategory($cid, $children = false) {
	if(empty($cid)) {
		return;
	}
	// get config
	$config = $GLOBALS['config'];
	// connect to db
	$db     = new MysqliDb($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
	// if cid is not empty, set the where clause
	if(!empty($cid)) {
		$db->where('id', $cid);
	} else {
		redirectTo($_SERVER['HTTP_REFERER']);
	}
	// get category
	$cat   = $db->getOne('categoria');

	// get children
	if($children) {
		$cat['subcategorias'] = loadCategories($cat['id'], array(5));
	}

	// return categories
	return $cat;
}

function saveCategory($post) {
	// if no post, return as ready
	if(count($post) == 0) {
		return array('status' => 'READY_TO_SUBMIT', 'category' => array());
	}

	// if no title, return error as title is required
	if(empty($post['titulo'])) {
		return array('status' => 'REQUIRED_FIELDS_EMPTY', 'fields' => array('titulo'));
	}

	// get config
	$config = $GLOBALS['config'];
	// connect to db
	$db     = new MysqliDb($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);

	// check if already exists a category with the same title
	if(empty($post['cid'])) {
		$db->where('titulo', $post['titulo']);
		if($db->get('categoria')) {
			// return error as dupplicated
			return array('status' => 'DUPPLICATED');
		}
	}

	// temp cat before saving
	$tmpCat = array();

	// fill temp with needed fields
	foreach($post as $p => $v) {
		if($p != 'guardar' && $p != 'action' && $p != 'cid' && $p != 'guadar-agregar' && $p != 'guadar-salir') {
			$tmpCat[$p] = $v;
		}
		if($p == 'guadar-agregar') {
			$action = 'agregar';
		}
		if($p == 'guadar-salir') {
			$action = 'salir';
		}
	}

	// save and get the new category id
	if(!empty($post['cid'])) {
		$db->where('id', $post['cid']);
		$saved = $db->update('categoria', $tmpCat);
		$cid   = $saved ? $post['cid'] : NULL;
	} else {
		$cid   = $db->insert('categoria', $tmpCat);
	}

	if($cid) {
		if($_FILES['imagen']['name'] == '') {
			return array('status' => 'SAVE_SUCCESS', 'category' => $tmpCat, 'action' => $action);
		}
		
		// Upload images
		$image = uploadImage($_FILES['imagen']);

		// save image url
		$db->where('id', $newCid ? $newCid : $cid);
		$db->update('categoria', array('imagen_url' => $image));

		// retgurn success and the new category
		return array('status' => 'SAVE_SUCCESS', 'category' => $tmpCat, 'action' => $action);
	} else {
		// if it isn't a new id, then some error has ocurred, return
		return array('status' => 'ERROR');
	}
}

function deleteCategory($cid) {
	changeCategoryStatus($cid, 7); // Eliminado
}

function publishCategory($cid) {
	changeCategoryStatus($cid, 5); // Público
}

function privateCategory($cid) {
	changeCategoryStatus($cid, 6); // Privado
}

function makeCategoryDraft($cid) {
	changeCategoryStatus($cid, 1); // Borrador
}

function changeCategoryStatus($cid, $statusId) {
	if(empty($cid)) {
		return;
	}

	// get config
	$config = $GLOBALS['config'];
	// connect to db
	$db     = new MysqliDb($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);

	$db->where('id', $cid);
	// get categories
	$cat = $db->getOne('categoria');

	$cat['estado'] = $statusId; // Eliminado
	$db->where('id', $cid);
	$db->update('categoria', $cat);
	redirectTo('/admin-pano/categories/');
}

function loadArticles($cid = null, $statusIds = null) {
	// get config
	$config = $GLOBALS['config'];
	// connect to db
	$db     = new MysqliDb($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
	// if cid is not empty, set the where clause
	if(isset($cid)) {
		$db->where('categoria_id', $cid);
	}

	$statusWhere = Array();
	for($s = 0; $s < count($statusIds); $s++) {
		$statusWhere[] = 'estado = ?';
	}

	$statusWhere = '('.implode(' or ', $statusWhere).')';

	// Where clause for status
	$db->where($statusWhere, $statusIds);

	// get categories
	$arts = $db->get('articulo');

	// return categories
	return $arts;
}

function loadArticle($aid) {
	if(empty($aid)) {
		return;
	}
	// get config
	$config = $GLOBALS['config'];
	// connect to db
	$db     = new MysqliDb($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
	// if aid is not empty, set the where clause
	if(!empty($aid)) {
		$db->where('id', $aid);
	} else {
		redirectTo($_SERVER['HTTP_REFERER']);
	}
	// get categories
	$art   = $db->getOne('articulo');

	// return categories
	return $art;
}

function saveArticle($post) {
	// if no post, return as ready
	if(count($_POST) == 0) {
		return array('status' => 'READY_TO_SUBMIT', 'article' => array());
	}

	// if no required fields, return error as fields are required
	if(empty($post['nombre']) OR empty($post['codigo']) OR empty($post['categoria_id'])) {
		return array('status' => 'REQUIRED_FIELDS_EMPTY', 'fields' => array('nombre', 'codigo', 'categoria_id'));
	}

	// get config
	$config = $GLOBALS['config'];
	// connect to db
	$db     = new MysqliDb($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);

	// check if already exists an article with the same name
	if(empty($post['aid'])) {
		$db->where('nombre', $post['nombre']);
		if($db->get('articulo')) {
			// return error as dupplicated
			return array('status' => 'DUPPLICATED');
		}
	}

	// temp art before saving
	$tmpArt = array();

	// fill temp with needed fields
	foreach($post as $p => $v) {
		if($p != 'guardar' && $p != 'action' && $p != 'aid' && $p != 'guadar-agregar' && $p != 'guadar-salir' && $p != 'PHPSESSID' && $p != 'cprelogin' && $p != 'cpsession' && $p != 'timezone' && $p != 'langedit' && $p != 'lang') {
			$tmpArt[$p] = $v;
		}
		if($p == 'guadar-agregar') {
			$action = 'agregar';
		}
		if($p == 'guadar-salir') {
			$action = 'salir';
		}
	}

	if(isset($post['nuevo'])) {
		$tmpArt['nuevo'] = 1;
	} else {
		$tmpArt['nuevo'] = 0;
	}
	if(isset($post['agotado'])) {
		$tmpArt['agotado'] = 1;
	} else {
		$tmpArt['agotado'] = 0;
	}
	if(isset($post['oferta'])) {
		$tmpArt['oferta'] = 1;
	} else {
		$tmpArt['oferta'] = 0;
	}
	if(isset($post['surtido'])) {
		$tmpArt['surtido'] = 1;
	} else {
		$tmpArt['surtido'] = 0;
	}

	debugging('POST ($_POST):', $post);

	// save and get the new category id
	if(!empty($post['aid'])) {
		$db->where('id', $post['aid']);
		$saved = $db->update('articulo', $tmpArt);
		$aid   = $saved ? $post['aid'] : NULL;
	} else {
		$aid   = $db->insert('articulo', $tmpArt);
	}

	if($aid) {
		if($_FILES['imagen']['name'] == '') {
			return array('status' => 'SAVE_SUCCESS', 'article' => $tmpArt, 'action' => $action);
		}
		
		// Upload images
		$image = uploadImage($_FILES['imagen']);

		// save image url
		$db->where('id', $newAid ? $newAid : $aid);
		$db->update('articulo', array('imagenes_url' => $image));

		// retgurn success and the new category
		return array('status' => 'SAVE_SUCCESS', 'article' => $tmpArt, 'action' => $action);
	} else {
		// if it isn't a new id, then some error has ocurred, return
		return array('status' => 'ERROR');
	}
}

function uploadImage($image) {
	// Upload temp file
	$upload = new upload($image);

	// if uploaded
	if($upload->uploaded) {
		$mediapath = $GLOBALS['relative'].'/uploads/';
		// if categories folder exists then
		if(file_exists($mediapath)) {
			// move temp file to the cat dir
			$upload->file_new_name_body = 'media_'.time();
			$upload->image_convert = 'jpg';
			$upload->process($mediapath);
		}
	}

	if(file_exists($mediapath.$upload->file_dst_name)) {
		return '/uploads/'.$upload->file_dst_name;
	}
	return false;
}

function deleteCartArticle($pid, $apid) {
	// get config
	$config = $GLOBALS['config'];
	// connect to db
	$db     = new MysqliDb($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
	// if pid and apid are not empty, set the wheres clause
	if(isset($pid) && isset($apid)) {
		$db->where('pedido_id', $pid);
		$db->where('id', $apid);
	} else {
		return;
	}

	// get the articulo_pedido
	$ap = $db->getOne('articulo_pedido');

	// set where clause to delete just this articulo_pedido
	$db->where('pedido_id', $pid);
	$db->where('id', $apid);
	// delete it
	$db->delete('articulo_pedido');

	// get the current pedido
	$db->where('id', $pid);
	$p = $db->getOne('pedido');

	// update the current pedido
	$p['cantidad'] = $p['cantidad'] - $ap['cantidad'];
	$p['total']    = $p['total'] - $ap['subtotal'];

	$db->where('id', $pid);
	if ($p['total'] > 0) {
		$db->update('pedido', $p);
	} else {
		$db->delete('pedido');
	}

	return;
}

function endCart($pid, $cart_html, $getInAgency, $buyInLocal = 0, $payForm = '', $placeIn = '', $address = '', $agency = '') {
	// get config
	$config = $GLOBALS['config'];
	// connect to db
	$db     = new MysqliDb($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);

	$db->where('id', $pid);
	$cart = $db->getOne('pedido');

	debugging('Pedido ($pid):', $pid);
	debugging('Pedido ($getInAgency):', $getInAgency);
	debugging('Pedido ($buyInLocal):', $buyInLocal);
	debugging('Pedido ($payForm):', $payForm);
	debugging('Pedido ($placeIn):', $placeIn);
	debugging('Pedido ($address):', $address);
	debugging('Pedido ($agency):', $agency);

	$cart['estado']               = 11;
	$cart['retira']               = $getInAgency;
	$cart['compra_en_local']      = $buyInLocal;
	$cart['direccion_de_entrega'] = $address;
	$cart['agencia_de_envio']     = $agency;
	$cart['agencia_de_envio']     = $agency;
	$cart['forma_de_pago']        = $payForm;
	$cart['lugar']                = $placeIn;

	debugging('Cart ($cart):', $cart);

	$db->where('id', $pid);
	$db->update('pedido', $cart);

	global $siteSettings, $userStats;
	debugging('Site Settings ($siteSettings):', $siteSettings);
	debugging('User ($userStats):', $userStats);

	$email_html   = array();
	$email_html[] = '<h1>Nuevo pedido online: Nº WEB'.$userStats['cart']['pedido']->id.'</h1>';
	$email_html[] = '<p><strong>Fecha y hora del pedido:</strong> <span>'.$userStats['cart']['pedido']->fecha.'</span></p>';
	$email_html[] = '<p><strong>Cantidad de artículos:</strong> <span>'.$userStats['cart']['pedido']->cantidad.'</span></p>';
	$email_html[] = '<p><strong>Monto del pedido:</strong> <span>$ '.number_format($userStats['cart']['pedido']->total, 2, ',', '').'</span></p>';
	$email_html[] = '<p><strong>A nombre de:</strong> <span>'.$userStats['user']->nombre.' '.$userStats['user']->apellido.'</span></p>';
	$email_html[] = '<p><strong>Teléfono de contacto:</strong> <span>'.$userStats['user']->telefono.' - '.$userStats['user']->celular.'</span></p>';
	$email_html[] = '<p><strong>Compra de(l):</strong> <span>'.ucfirst ($placeIn).'</span></p>';
	$email_html[] = '<p><strong>Retira y paga en el local:</strong> <span>'.($buyInLocal ? 'Si' : 'No').'</span></p>';
	$email_html[] = (!$buyInLocal && $placeIn == 'interior' ? '<p><strong>Agencia de envío:</strong> <span>'.ucfirst (strtolower ($agency)).'</span></p>' : '');
	$email_html[] = (!$buyInLocal ? '<p><strong>Dirección de entrega:</strong> <span>'.$address.'</span></p>' : '');
	$email_html[] = (!$buyInLocal ? '<p><strong>Forma de pago:</strong> <span>'.ucfirst ($payForm).'</span></p>' : '');
	$email_html[] = (!$buyInLocal && $payForm == 'abitab' || $payForm == 'redpagos' ? '<h3><strong>Pagar a Mario Mendlowicz 4.254.755-9</strong></h3>' : '');
	$email_html[] = (!$buyInLocal && $payForm == 'brou' ? '<h3><strong>Depósito a cuenta BROU de Ladenix SA: 181- 18871</strong></h3>' : '');
	$email_html[] = (!$buyInLocal && $payForm == 'brou' ? '<p><strong>Expeficicar Nº de orden como referencia de pago (WEB'.$userStats['cart']['pedido']->id.')</strong></p>' : '');
	$email_html[] = $cart_html;

	$vendorEmail  = array (
		'address'      => $siteSettings['vendedor-email'],
		'address_name' => $siteSettings['nombre'].' ('.$siteSettings['dominio'].')',
		'bcc_mail'     => $siteSettings['admin-email'],
		'from'         => array (
			'email'   => $userStats['user']->email,
			'name'    => $userStats['user']->nombre.' '.$userStats['user']->apellido,
			'contact' => $userStats['user']->nombre.' '.$userStats['user']->apellido.' <'.$userStats['user']->email.'>'
		),
		'subject'      => $siteSettings['nombre'].' - Pedidos online',
		'content'      => implode ('', $email_html)
	);

	$userEmail   = array (
		'address'      => $userStats['user']->email,
		'address_name' => $userStats['user']->nombre.' '.$userStats['user']->apellido,
		'from'         => array (
			'email'   => $siteSettings['vendedor-email'],
			'name'    => $siteSettings['nombre'],
			'contact' => $siteSettings['nombre'].' <'.$siteSettings['vendedor-email'].'>'
		),
		'subject'      => $siteSettings['nombre'].' - Pedidos online',
		'content'      => implode ('', $email_html)
	);

	$admin_notifications = (array) json_decode ($siteSettings['notificaciones']);

	debugging('Mail ($mail):', $mail);
	debugging('Nueva Venta ($admin_notifications):', ($admin_notifications['nueva-venta'] == 'on'));

	sentEmail($vendorEmail, ($admin_notifications['nueva-venta'] == 'on'));
	sentEmail($userEmail, ($admin_notifications['nueva-venta'] == 'on'));

	return true;
}

function sentEmail($data_email, $admin_notification = false) {
	global $siteSettings;

	$mail = new PHPMailer(true);

	// Fix new server error @Could not instantiate mail function.
	$mail->isSMTP();
	$mail->Host       = 'mail.unifit.com.uy';
	$mail->SMTPAuth   = true;
	$mail->Username   = 'unifit@unifit.com.uy';
	$mail->Password   = 'R]zIE3G}3i*7';
	$mail->Port       = 587;
	// End fix

	$mail->addAddress($data_email['address'], $data_email['address_name']);

	if (isset ($data_email['bcc_mail']) && $data_email['bcc_mail'] != '' && $admin_notification) {
		$mail->addBCC($data_email['bcc_mail']);
		$mail->addBCC('miguelmail2006@gmail.com');
	}

	$mail->setFrom('unifit@unifit.com.uy', 'Website ('.$siteSettings['dominio'].' - '.$data_email['from']['contact'].')');
	$mail->addReplyTo($data_email['from']['email'], $data_email['from']['name']);
	$mail->Subject = '('.$siteSettings['dominio'].') '.utf8_decode ($data_email['subject']);
	$mail->msgHTML(utf8_decode ($data_email['content']));

	debugging('PHPMailer ($mail):', $mail);

	try {
		$mail->send();
		$sent = true;
	} catch (phpmailerException $e) {
		echo $e->errorMessage();
		$sent = false;
	} catch (Exception $e) {
	 	echo $e->getMessage();
		$sent = false;
	}

	return $sent;
}

function getStatusValue($sid) {
	global $config;

	$db = new MysqliDb($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);

	$db->where('id', $sid);
	$status = $db->getValue('estado', 'estado', 1);

	return $status;
}

?>