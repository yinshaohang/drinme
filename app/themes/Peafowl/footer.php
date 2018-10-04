<?php if(!defined('access') or !access) die('This file cannot be directly accessed.'); ?>

<?php if(!is_maintenance()) {  G\Render\include_theme_file('snippets/embed_tpl'); } ?>

<?php
if(is_upload_allowed()) {
	G\Render\include_theme_file('snippets/anywhere_upload');
}
?>

<?php
if(!CHV\Login::isLoggedUser()) {
	G\Render\include_theme_file('snippets/modal_login');
}
?>

<?php G\Render\include_theme_file('custom_hooks/footer'); ?>

<?php CHV\Render\include_peafowl_foot(); ?>

<?php CHV\Render\show_theme_inline_code('snippets/footer.js'); ?>

<?php CHV\Render\showQueuePixel(); ?>

<?php CHV\Render\showPingPixel(); ?>

<?php echo CHV\getSetting('analytics_code'); ?>

<script type="text/javascript">var cnzz_protocol = (("https:" == document.location.protocol) ? " https://" : " http://");document.write(unescape("%3Cspan id='cnzz_stat_icon_1260976975'%3E%3C/span%3E%3Cscript src='" + cnzz_protocol + "s4.cnzz.com/z_stat.php%3Fid%3D1260976975%26show%3Dpic1' type='text/javascript'%3E%3C/script%3E"));</script>
</body>
<?php include_once("views/baidu_js_push.php") ?>
</html>
