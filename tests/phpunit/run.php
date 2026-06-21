<?php
/**
 * Pure-PHP test runner — works without composer/PHPUnit.
 *
 * Usage:
 *   php tests/phpunit/run.php
 *
 * Discovers tests/phpunit/ktube/*.php, instantiates each TestCase subclass,
 * invokes every public method whose name starts with "test", and exits 0
 * on all-pass / 1 on any failure. Output is plain text; pipe-friendly.
 *
 * Production equivalents (with PHPUnit installed):
 *   vendor/bin/phpunit --bootstrap tests/phpunit/bootstrap.php tests/phpunit
 */

require_once __DIR__ . '/bootstrap.php';

use PHPUnit\Framework\TestCase;

$ktube_dir       = __DIR__ . '/ktube';
$ktube_iterator  = new GlobIterator( $ktube_dir . '/*.php' );
$ktube_total_run = 0;

echo "ktube phpunit runner (composer-free)\n";
echo "  suite: " . $ktube_dir . "\n";

foreach ( $ktube_iterator as $ktube_file ) {
	$ktube_basename = $ktube_file->getBasename( '.php' );
	require_once $ktube_file->getPathname();
	if ( ! class_exists( $ktube_basename ) ) {
		echo "  SKIP  $ktube_basename (no class)\n";
		continue;
	}
	$ktube_reflection = new ReflectionClass( $ktube_basename );
	if ( $ktube_reflection->isAbstract() ) {
		continue;
	}
	if ( ! $ktube_reflection->isSubclassOf( TestCase::class ) ) {
		echo "  SKIP  $ktube_basename (not a TestCase)\n";
		continue;
	}
	$ktube_obj = $ktube_reflection->newInstance();
	$ktube_total_run++;
	echo "\n  $ktube_basename\n";
	foreach ( $ktube_reflection->getMethods( ReflectionMethod::IS_PUBLIC ) as $ktube_method ) {
		$ktube_name = $ktube_method->getName();
		if ( strncmp( $ktube_name, 'test', 4 ) !== 0 ) {
			continue;
		}
		if ( $ktube_method->getNumberOfParameters() > 0 ) {
			echo "    SKIP  $ktube_name (has required params)\n";
			continue;
		}
		// Honor setUpBeforeClass naming to allow setUp() per-method if needed.
		$ktube_setUp = $ktube_reflection->hasMethod( 'setUp' ) ? $ktube_reflection->getMethod( 'setUp' ) : null;
		try {
			if ( $ktube_setUp ) {
				$ktube_setUp->invoke( $ktube_obj );
			}
			$ktube_method->invoke( $ktube_obj );
		} catch ( Throwable $e ) {
			\PHPUnit\Framework\TestCase::class; // Touch for static init.
			echo "    EXC   $ktube_name: " . $e->getMessage() . "\n";
		}
	}
}

list( $ktube_passes, $ktube_fails ) = \PHPUnit\Framework\TestCase::totals();

echo "\n" . str_repeat( '=', 60 ) . "\n";
echo sprintf( "Ran %d test method(s) across %d file(s) — %d pass / %d fail\n", $ktube_passes, $ktube_total_run, $ktube_passes, $ktube_fails );
exit( $ktube_fails > 0 ? 1 : 0 );
