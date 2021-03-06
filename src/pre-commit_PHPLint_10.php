<?php

/**
 * Git Hook to disallow PHP syntax errors to be commited
 * by running php -l (PHP Lint) on yet-to-be-commited files.
 */

# Fetch current commit state
$files  = array();
$return = 0;
exec( "git rev-parse --verify HEAD 2> /dev/null", $set, $return );

# Grab commited files
$against = $return === 0
	? 'HEAD'
	// or: diff against an empty tree object
	: '4b825dc642cb6eb9a060e54bf8d69288fbee4904';

exec( "git diff-index --cached --full-index {$against}", $files );


echo "\n--------------------------\n";
echo " Running PHP Lint\n";
echo "--------------------------\n";

$pattern = '/\.ph(tml|p)$/';
$exit_status = 0;
foreach ( $files as $file )
{
	$parts  = explode( " ", $file );
	$sha    = $parts[3];
	$name   = substr( $parts[4], 2 );
	$status = substr( $parts[4], 0, 1 );
	$path   = str_replace( "{$status}\t", "", $parts[4] );

	// don't check files that aren't PHP
	if ( ! preg_match( $pattern, $name ) )
		continue;

	// if the file has been moved or deleted,
	// the old filename should be skipped
	if ( ! file_exists( $name ) )
		continue;

	// Unmerged
	if ( 'U' === $status )
	{
		echo " |- {$name} is unmerged. You must complete the merge before it can be committed.\n";
		continue;
	}

	// Internal Git Bug
	if ( 'X' === $status )
	{
		echo " |- {$name}: unknown status. Please file a bug report for git. Really.\n";
		continue;
	}

	// If the file was deleted, skip it
	if ( 'D' === $status )
		continue;

	$output = array();
	$result = '';
	// Grab the file from the list of files in the commit
	$cmd = sprintf(
		"git cat-file -p %s | php -l -ddisplay_errors\=1 -derror_reporting\=E_ALL -dlog_errors\=0",
		escapeshellarg( $sha )
	);
	exec( $cmd, $output, $result );
	if ( -1 === $result )
	{
		foreach ( $output as $line )
		{
			if ( empty( $line ) )
				continue;

			if ( ! strstr( $line, ':' ) )
				continue;

			echo preg_replace(
				'/\s(in)\s/i',
				" in {$name} ",
				" |- {$line}\n"
			);
		}

		$exit_status = 1;
	}
	else
	{
		echo " |- {$name}\n";
	}
}

if ( 0 === $exit_status )
{
	echo "--------------------------\n";
	echo " <3 All files lint free.\n";
	echo "--------------------------\n\n";
}
else
{
	echo "-----------------------------------------\n";
	echo " Please fix all errors before commiting.\n";
	echo "-----------------------------------------\n\n";
	# End and abort
	exit( $exit_status );
}
