<?php

namespace Vendi\Cache\Legacy;

use Vendi\Cache\cache_setting_exception;
use Vendi\Cache\cache_settings;

if( ! VENDI_CACHE_SUPPORT_MU && defined( 'MULTISITE' ) && MULTISITE )
{
    echo '<div class="wrap"><h1>Multisite is not currently supported in this release.</h1></div>';
    return;
}

$vwc_settings = \Vendi\Cache\cache_settings::get_instance( );

?>
<div id="vendi_caching" style="display: none;"></div>
<div class="wrap">
	<h1><?php echo esc_html( VENDI_CACHE_PLUGIN_NAME ); ?></h1>
	<div>

		<div class="section">

			<h2><?php esc_html_e( 'Cache Mode', 'Vendi Cache' ); ?></h2>

			<table border="0">
				<tr>
					<td><?php esc_html_e( 'Disable all performance enhancements:', 'Vendi Cache' ); ?></td>
					<td><input type="radio" name="cacheType" value="<?php echo cache_settings::CACHE_MODE_OFF; ?>" <?php if( cache_settings::CACHE_MODE_OFF === $vwc_settings->get_cache_mode() ) { echo 'checked="checked"'; } ?> /></td>
					<td><?php esc_html_e( 'No performance improvement', 'Vendi Cache' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Enable basic caching:', 'Vendi Cache' ); ?></td>
					<td><input type="radio" name="cacheType" value="php" <?php if( cache_settings::CACHE_MODE_PHP === $vwc_settings->get_cache_mode() ) { echo 'checked="checked"'; } ?> /></td>
					<td><?php esc_html_e( '2 to 3 Times speed increase', 'Vendi Cache' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Enable disk-based cache:', 'Vendi Cache' ); ?></td>
					<td><input type="radio" name="cacheType" value="<?php echo cache_settings::CACHE_MODE_ENHANCED; ?>" <?php if( cache_settings::CACHE_MODE_ENHANCED === $vwc_settings->get_cache_mode() ) { echo 'checked="checked"'; } ?> /></td>
					<td><?php esc_html_e( '30 to 50 Times speed increase', 'Vendi Cache' ); ?></td>
				</tr>
			</table>

			<input type="button" id="button1" name="button1" class="button-primary" value="<?php esc_attr_e( 'Save changes to the type of caching enabled above', 'Vendi Cache' ); ?>" onclick="VCAD.saveCacheConfig();" />

		</div>

		<div class="section">

			<h2>Cache Options</h2>

			<table border="0">
				<tr>
					<td><?php esc_html_e( 'Allow TLS (secure HTTPS pages) to be cached:', 'Vendi Cache' ); ?></td>
					<td>
						<input type="checkbox" id="wfallowHTTPSCaching" value="1" <?php if( $vwc_settings->get_do_cache_https_urls() ) { echo 'checked="checked"'; } ?> />
						<?php esc_html_e( 'We recommend you leave this disabled unless your site uses HTTPS but does not receive/send sensitive user info.', 'Vendi Cache' ); ?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Add hidden debugging data to the bottom of the HTML source of cached pages:', 'Vendi Cache' ); ?></td>
					<td>
						<input type="checkbox" id="wfaddCacheComment" value="1" <?php if( $vwc_settings->get_do_append_debug_message() ) { echo 'checked="checked"'; } ?> />
						<?php esc_html_e( 'Message appears as an HTML comment below the closing HTML tag.', 'Vendi Cache' ); ?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Clear cache when a scheduled post is published:', 'Vendi Cache' ); ?></td>
					<td>
						<input type="checkbox" id="wfclearCacheSched" value="1" <?php if( $vwc_settings->get_do_clear_on_save() ) { echo 'checked="checked"'; } ?> />
						<?php esc_html_e( 'The entire disk-based cache will be cleared when WordPress publishes a post you\'ve scheduled to be published in future.', 'Vendi Cache' ); ?>
					</td>
				</tr>
			</table>

			<br />

			<input type="button" id="button1" name="button1" class="button-primary" value="<?php esc_attr_e( 'Save changes to the the caching options above', 'Vendi Cache' ); ?>" onclick="VCAD.saveCacheOptions();" />

		</div>

		<div class="section">

			<h2><?php esc_html_e( 'Cache Management', 'Vendi Cache' ); ?></h2>

			<p style="width: 500px;">
				<input type="button" id="button1" name="button1" class="button-primary" value="<?php esc_attr_e( 'Clear the cache', 'Vendi Cache' ); ?>" onclick="VCAD.clearPageCache();" />
				&nbsp;&nbsp;
				<input type="button" id="button1" name="button1" class="button-primary" value="<?php esc_attr_e( 'Get cache stats', 'Vendi Cache' ); ?>" onclick="VCAD.getCacheStats();" />
				<br />
				<?php esc_html_e( 'Note that the cache is automatically cleared when administrators make any site updates. Some of the actions that will automatically clear the cache are:', 'Vendi Cache' ); ?>
				<ul>
					<li><?php esc_html_e( 'Publishing a post', 'Vendi Cache' ); ?></li>
					<li><?php esc_html_e( 'Creating a new page', 'Vendi Cache' ); ?></li>
					<li><?php esc_html_e( 'Updating general settings', 'Vendi Cache' ); ?></li>
					<li><?php esc_html_e( 'Creating a new category', 'Vendi Cache' ); ?></li>
					<li><?php esc_html_e( 'Updating menus', 'Vendi Cache' ); ?></li>
					<li><?php esc_html_e( 'Updating widgets', 'Vendi Cache' ); ?></li>
					<li><?php esc_html_e( 'Installing a new plugin.', 'Vendi Cache' ); ?></li>
				</ul>
			</p>

			<h3><?php esc_html_e( 'You can add items like URLs, cookies and browsers (user-agents) to exclude from caching', 'Vendi Cache' ); ?></h3>

			<!-- Not sure what the best way to get translators access to this. Anyone have any ideas? -->
			<p style="width: 500px; white-space:nowrap;">
				If a
					<select id="wfPatternType">
						<option value="<?php echo cache_setting_exception::URL_STARTS_WITH; ?>">URL Starts with</option>
						<option value="<?php echo cache_setting_exception::URL_ENDS_WITH; ?>">URL Ends with</option>
						<option value="<?php echo cache_setting_exception::URL_CONTAINS; ?>">URL Contains</option>
						<option value="<?php echo cache_setting_exception::URL_MATCHES_EXACTLY; ?>">URL Exactly Matches</option>
						<option value="<?php echo cache_setting_exception::USER_AGENT_CONTAINS; ?>">User-Agent Contains</option>
						<option value="<?php echo cache_setting_exception::USER_AGENT_MATCHES_EXACTLY; ?>">User-Agent Exactly Matches</option>
						<option value="<?php echo cache_setting_exception::COOKIE_NAME_CONTAINS; ?>">Cookie Name Contains</option>
					</select>
				this value<br />then don't cache it:
				<input type="text" id="wfPattern" value="" size="20" maxlength="1000" />e.g. /my/dynamic/page/
				<input type="button" class="button-primary" value="<?php esc_attr_e( 'Add exclusion', 'Vendi Cache' ); ?>" onclick="VCAD.addCacheExclusion(jQuery('#wfPatternType').val(), jQuery('#wfPattern').val()); return false;" />
			</p>

			<div id="wfCacheExclusions">

			</div>

		</div>
	</div>

</div>

<!-- Not sure what the best way to get translators access to this -->
<script type="text/x-jquery-template" id="wfCacheExclusionTmpl">
<div>
	If the
	<strong style="color: #0A0;">
	{{if pt == 's'}}
	URL starts with
	{{else pt == 'e'}}
	URL ends with
	{{else pt =='c'}}
	URL contains
	{{else pt == 'eq'}}
	URL equals
	{{else pt == 'uac'}}
	User-Agent contains
	{{else pt == 'uaeq'}}
	User-Agent equals
	{{else pt == 'cc'}}
	Cookie Name contains
	{{else pt == 'ceq'}}
	Cookie Name equals
	{{else pt == 'ipeq'}}
	IP Address equals
	{{/if}}
	</strong>
	(without quotes):
	<strong style="color: #F00;">
	"${p}"
	</strong>
	then don't cache it. [<a href="#" onclick="VCAD.removeCacheExclusion('${id}'); return false;">remove exclusion</a>]
</div>
</script>
