# Vendi Cache 
**Contributors:** chrisvendiadvertisingcom  
**Tags:** cache, caching, disk cache, disk caching, page cache, performance, plugin  
**Requires at least:** 3.9  
**Tested up to:** 4.7.0  
**Stable tag:** 1.2.5 0
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

Vendi Cache is a disk-based cache plugin derived from Wordfence's caching engine.


## Description 

Vendi Cache takes your slow database-driven pages and turns them into very fast static HTML files.

*Squirrel image from https://commons.wikimedia.org/wiki/File:Tamias_striatus_CT.jpg*


## Installation 

Before activating this plugin, please disable Wordfence's caching system.


## Screenshots 

### 1. Settings for cache mode and options.
![Settings for cache mode and options](https://ps.w.org/vendi-cache/assets/screenshot-1.png?rev=1529164)

### 2. Cache management and exclusions.
![Cache management and exclusions](https://ps.w.org/vendi-cache/assets/screenshot-2.png?rev=152916)4

### 3. Admin menu location.
![Admin menu location](https://ps.w.org/vendi-cache/assets/screenshot-3.png?rev=1529164)



## Frequently Asked Questions 


### Is this plugin affiliated with Wordfence? 

The authors of this plugin are not affiliated with Wordfence. The caching engines used by this plugin were ported from Wordfence's code after they decided to remove them from their codebase.


### Does this plugin replace Wordfence? 

No, but it does replace a feature that they will be removing soon. <a href="https://wordpress.org/plugins/wordfence/">Wordfence</a> is one of the best security plugins available for WordPress and for about 2 years their product included a very awesome caching engine. In October of 2016 they decided that they would be removing this caching engine which is why this plugin was created.

### Can I programmatically stop Vendi Cache from caching a specific page/post? ###

Yes, the caching engine may be stopped for a given request in several ways. The preferred way (as of 1.2.0) is via a filter: `add_filter( \Vendi\Cache\api::FILTER_NAME_DO_NOT_CACHE, '__return_true' )`. For legacy reasons we also still support a public static method `\Vendi\Cache\api::do_not_cache()`. Lastly, if you wish to globally stop the caching engine site-wide you can define the `WFDONOTCACHE` constant somewhere in your code.

For historical reasons, if defined the global constant is always honored first and cannot be undone via the filter. This might be changed in the future if someone actually has a need for it but generally speaking, constants are used to make global changes by administrators that local code should not be able to undo.


## Changelog

### 1.2.0
* Add API class for all future public contracts.
* Add filter `\Vendi\Cache\api::FILTER_NAME_DO_NOT_CACHE` (preferred) and function `\Vendi\Cache\api::do_not_cache()` (legacy) to allow people to disable caching per request.
* Add `cache_stats` class to use instead of array, make strings translatable.
* Add `clear_entire_cache` to public API.
* Deprecate Vendi\Cache\Legacy\wordfence::do_not_cache() in favor of above.

### 1.1.5
* Do not cache if PHP throws a fatal exception or error in someone else's code.


### 1.1.2
* Inlined a PHP constant
* Fixed a couple of typos (props laxbobber)


### 1.1.0 
* First public release


### 1.0.1 
* First private beta.

