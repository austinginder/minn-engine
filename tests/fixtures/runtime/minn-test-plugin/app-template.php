<?php
// The fixture app document: deterministic output both stacks must serve
// byte for byte when the rewrite rule routes here.
$route = (string) get_query_var('minn_test_route');
?><!DOCTYPE html>
<html><head><title>Minn Test App</title></head>
<body class="minn-test-app">
<h1>Minn Test App</h1>
<p id="route"><?php echo esc_html($route === '' ? '(root)' : $route); ?></p>
<p id="site"><?php echo esc_html((string) get_option('blogname')); ?></p>
</body></html>
