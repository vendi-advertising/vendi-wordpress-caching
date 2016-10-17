<?php

//Legacy autoloader
spl_autoload_register(
                        function( $class )
                        {
                            $legacy = array(
                                                'Vendi\\Wordfence\\Caching\\wfCache'   => 'wfCache.php',
                                                'Vendi\\Wordfence\\Caching\\wfConfig'  => 'wfConfig.php',
                                                'Vendi\\Wordfence\\Caching\\wfUtils'   => 'wfUtils.php',
                                                'Vendi\\Wordfence\\Caching\\wfDB'      => 'wfDB.php',
                                                'Vendi\\Wordfence\\Caching\\wfSchema'  => 'wfSchema.php',
                                                'Vendi\\Wordfence\\Caching\\wordfence' => 'wordfenceClass.php',
                                        );

                            if( array_key_exists( $class, $legacy ) )
                            {
                                require_once VENDI_WORDPRESS_CACHING_PATH . '/lib/' . $legacy[ $class ];
                            }
                        }
                    );

//New autoloader
spl_autoload_register(
                        function ( $class )
                        {
                            //PSR-4 compliant autoloader
                            //See http://www.php-fig.org/psr/psr-4/
                            $prefixes = array(
                                                'Vendi\\WordPress\\Caching\\' => VENDI_WORDPRESS_CACHING_PATH . '/lib/Vendi/WordPress/Caching/',
                                                'Vendi\\Shared\\'             => VENDI_WORDPRESS_CACHING_PATH . '/lib/Vendi/Shared/',
                                            );

                            foreach( $prefixes as $prefix => $base_dir )
                            {
                                // does the class use the namespace prefix?
                                $len = strlen( $prefix );
                                if ( 0 !== strncmp( $prefix, $class, $len ) )
                                {
                                    // no, move to the next registered prefix
                                    continue;
                                }

                                // get the relative class name
                                $relative_class = substr( $class, $len );

                                // replace the namespace prefix with the base directory, replace namespace
                                // separators with directory separators in the relative class name, append
                                // with .php
                                $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

                                // if the file exists, require it
                                if ( file_exists( $file ) )
                                {
                                    require_once $file;
                                }
                            }
                        }
                    );
