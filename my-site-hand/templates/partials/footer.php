<?php
/**
 * Admin page footer partial.
 *
 * @package MySiteHand
 */

defined( 'ABSPATH' ) || exit;
?>
<footer class="msh-footer">
	<div class="msh-footer-left">
		<strong>My Site Hand <?php echo esc_html( MYSITEHAND_VERSION ); ?></strong>
		<span class="msh-footer-sep">&bull;</span>
		<a href="https://modelcontextprotocol.io" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'MCP Specification', 'my-site-hand' ); ?></a>
	</div>
	<div class="msh-footer-right">
		<span>WordPress <?php echo esc_html( get_bloginfo( 'version' ) ); ?></span>
		<span class="msh-footer-sep">&bull;</span>
		<span>PHP <?php echo esc_html( PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ); ?></span>
	</div>
</footer>
