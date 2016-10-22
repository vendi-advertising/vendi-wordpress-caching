<?php

namespace Vendi\Cache\Legacy;

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
	<?php
    if (cache_settings::CACHE_MODE_ENHANCED === $vwc_settings->get_cache_mode())
    {
        echo '<div title="Vendi Cache Disk-based cache enabled" class="wfFalcon"></div>';
    }
    ?>
	<h2>Vendi Cache</h2>
	<div class="wordfenceWrap" style="margin: 20px 20px 20px 30px; max-width: 800px;">
		<div id="wordfenceFalconDeprecationWarning">
			<p>
				This plugin should replicate all Wordfence cache-related settings.
			</p>
		</div>
		<h2>Caching</h2>
		<table border="0">
		<tr><td>Disable all performance enhancements:</td><td><input type="radio" name="cacheType" id="cacheType_disable" value="<?php echo cache_settings::CACHE_MODE_OFF; ?>" <?php if (cache_settings::CACHE_MODE_OFF === $vwc_settings->get_cache_mode()) { echo 'checked="checked"'; } ?> /></td><td>No performance improvement</td></tr>
		<tr><td>Enable Basic Caching:</td><td><input type="radio" name="cacheType" id="cacheType_php" value="php" <?php if (cache_settings::CACHE_MODE_PHP === $vwc_settings->get_cache_mode()) { echo 'checked="checked"'; } ?> /></td><td>2 to 3 Times speed increase</td></tr>
		<tr><td>Enable Wordfence Falcon Engine:<div class="wfSmallFalcon"></div></td><td><input type="radio" name="cacheType" id="cacheType_falcon" value="<?php echo cache_settings::CACHE_MODE_ENHANCED; ?>" <?php if (cache_settings::CACHE_MODE_ENHANCED === $vwc_settings->get_cache_mode()) { echo 'checked="checked"'; } ?> /></td><td>30 to 50 Times speed increase</td></tr>
		</table>
		<br />
		<input type="button" id="button1" name="button1" class="button-primary" value="Save Changes to the type of caching enabled above" onclick="WFAD.saveCacheConfig();" />
		<h2>Cache Options</h2>
		<table border="0">
		<tr><td>Allow SSL (secure HTTPS pages) to be cached:</td><td><input type="checkbox" id="wfallowHTTPSCaching" value="1" <?php if ($vwc_settings->get_do_cache_https_urls()) { echo 'checked="checked"'; } ?> />We recommend you leave this disabled unless your<br />site uses HTTPS but does not receive/send sensitive user info.</td></tr>
		<tr><td>Add hidden debugging data to the bottom of the HTML source of cached pages:</td><td><input type="checkbox" id="wfaddCacheComment" value="1" <?php if ($vwc_settings->get_do_append_debug_message()) { echo 'checked="checked"'; } ?> />Message appears as an HTML comment below the closing HTML tag.</td></tr>
		<tr><td>Clear cache when a scheduled post is published</td><td><input type="checkbox" id="wfclearCacheSched" value="1" <?php if ($vwc_settings->get_do_clear_on_save()) { echo 'checked="checked"'; } ?> />The entire Falcon cache will be cleared when WordPress publishes a post you've scheduled to be published in future.</td></tr>
		</table>
		<br />
		<input type="button" id="button1" name="button1" class="button-primary" value="Save Changes to the the caching options above" onclick="WFAD.saveCacheOptions();" />
		<br /><br />
		<h2>Cache Management</h2>
		<p style="width: 500px;">
			<input type="button" id="button1" name="button1" class="button-primary" value="Clear the Cache" onclick="WFAD.clearPageCache();" />
			&nbsp;&nbsp;
			<input type="button" id="button1" name="button1" class="button-primary" value="Get Cache Stats" onclick="WFAD.getCacheStats();" />
			<br />
			Note that the cache is automatically cleared when administrators make any site updates. Some
			of the actions that will automatically clear the cache are:<br />
			Publishing a post, creating a new page, updating general settings, creating a new category, updating menus, updating widgets and installing a new plugin.
		</p>
		<h2>You can add items like URLs, cookies and browsers (user-agents) to exclude from caching</h2>
		<p style="width: 500px; white-space:nowrap;">
			If a 
			<select id="wfPatternType">
				<option value="s">URL Starts with</option>
				<option value="e">URL Ends with</option>
				<option value="c">URL Contains</option>
				<option value="eq">URL Exactly Matches</option>
				<option value="uac">User-Agent Contains</option>
				<option value="uaeq">User-Agent Exactly Matches</option>
				<option value="cc">Cookie Name Contains</option>
			</select>
			this value<br>then don't cache it:
			<input type="text" id="wfPattern" value="" size="20" maxlength="1000" />e.g. /my/dynamic/page/
			<input type="button" class="button-primary" value="Add exclusion" onclick="WFAD.addCacheExclusion(jQuery('#wfPatternType').val(), jQuery('#wfPattern').val()); return false;" />
		</p>
		<div id="wfCacheExclusions">

		</div>
	</div>

</div>
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
	then don't cache it. [<a href="#" onclick="WFAD.removeCacheExclusion('${id}'); return false;">remove exclusion</a>]
</div>
</script>
<script type="text/x-jquery-template" id="wfWelcomeContentCaching">
<div>
<h3>How to speed up your site by up to 50 times</h3>
<strong><p>Wordfence includes Falcon Engine, the fastest WordPress caching system available.</p></strong>
<p>
	Having a fast site is important for several reasons. Firstly it will cause google to rank you higher in the search results. Google have publicly stated
	that site speed is an important factor in search engine ranking. Secondly, it protects you from a denial of service attack. If a hacker is accessing your site at
	20 pages per second and your site can't keep up, your site will appear to be unavailable to all other visitors. But if your site
	can easily handle up to 800 requests per second, a hacker consuming 20 pages per second won't affect everyone else. That is why Wordfence
	includes Falcon Engine, the fastest WordPress caching engine available.
</p>
<p>
	You can enable Falcon Engine on this page. If for some reason your site is not compatible with Falcon, you can still enable our basic caching which will also
	give you a significant performance boost.
</p>
</div>
</script>
