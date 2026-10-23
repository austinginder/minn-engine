<?php
/**
 * The footer a classic theme without footer.php gets: noted as deprecated,
 * then the site's "powered by" line, the closing page, and wp_footer.
 */

_deprecated_file('Theme without footer.php', '3.0.0', null, 'Please include a footer.php template in your theme.');
?>

<hr />
<div id="footer" role="contentinfo">
<!-- If you'd like to support WordPress, having the "powered by" link somewhere on your blog is the best way; it's our only promotion or advertising. -->
	<p>
		<?php echo get_bloginfo('name') . ' is proudly powered by <a href="https://wordpress.org/">WordPress</a>'; ?>
	</p>
</div>
</div>

<!-- Gorgeous design by Michael Heilemann - http://binarybonsai.com/ -->

		<?php wp_footer(); ?>
</body>
</html>
