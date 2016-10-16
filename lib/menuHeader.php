<?php

namespace Vendi\Wordfence\Caching;

if( 'falcon' === wfConfig::get( 'cacheType' ) )
{
    echo '<div title="Wordfence Falcon Engine Enabled for Maximum Site Performance" class="wfFalcon"></div>';
}
