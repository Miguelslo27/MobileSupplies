<?php

function validateEmail($email = null) {
	return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function redirectTo($url, $attrs = null, $keepOlds = false) {
	$oldAttrs = null;
	$newAttrs = array();

	if($keepOlds) {
		$oldAttrs = getAttributesFrom($url, true);
		$oldAttrs = implode('&', $oldAttrs);
	}

	if($attrs) {
		foreach ($attrs as $key => $value) {
			$newAttrs[] = $key.'='.$value;
		}
		$newAttrs = implode('&', $newAttrs);
	}

	$url = cleanUpUrl($url);
	
	if($oldAttrs || $newAttrs) {
		$url .= '?';
		if($oldAttrs) {
			$url .= $oldAttrs;
		}
		if($newAttrs) {
			$url .= ($oldAttrs ? '&' : '').$newAttrs;
		}
	}

	header('Location: '.$url);
}

function getAttributesFrom($url, $asString = false) {
	$attrs = explode('?', $url)[1];

	if($asString) {
		return explode('&', $attrs);
	}

	$return = array();
	foreach(explode('&', $attrs) as $attr) {
		$return[] = explode('=', $attr);
	}

	return $return;
}

function cleanUpUrl($url) {
	return explode('?', $url)[0];
}

function debugging($message, $expression = '', $force = false) {
	if ((isset ($_REQUEST['d']) && $_REQUEST['d'] == 't') || $force) {
	?>

	<div class="debug">
		<strong class="debug-message">
			<pre><?php echo $message ?></pre>
		</strong>
		<?php if ($expression != '') : ?>
		<pre class="debug-expression"><?php print_r ($expression) ?></pre>
		<?php endif ?>
	</div>

	<?php
	}
}

?>