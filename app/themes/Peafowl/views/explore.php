<?php if(!defined('access') or !access) die('This file cannot be directly accessed.'); ?>
<?php G\Render\include_theme_header(); ?>

<?php CHV\Render\show_banner('explore_after_top', get_list()->sfw); ?>

<div class="content-width">
	
	<div class="header header-tabs margin-bottom-10 follow-scroll">
    	<h1><strong><?php echo (function_exists('get_category') and get_category()['name']) ? get_category()['name'] : _s('Explore'); ?></strong></h1>
		
        <?php G\Render\include_theme_file("snippets/tabs"); ?>
        
		<?php
			if(is_admin()) {
				G\Render\include_theme_file("snippets/user_items_editor");
		?>
        <div class="header-content-right phone-float-none">
			<?php G\Render\include_theme_file("snippets/listing_tools_editor"); ?>
        </div>
		<?php
			}
		?>
        
    </div>
    
    <div id="content-listing-tabs" class="tabbed-listing">
        <div id="tabbed-content-group">
            <?php
                G\Render\include_theme_file("snippets/listing");
            ?>
        </div>
    </div>
	
</div>

<?php G\Render\include_theme_footer(); ?>