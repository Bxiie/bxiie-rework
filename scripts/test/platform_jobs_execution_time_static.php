<?php

declare(strict_types=1);
$root=dirname(__DIR__,2);
$controller=file_get_contents($root.'/app/Http/Controllers/Platform/Admin/JobsController.php');
$repo=file_get_contents($root.'/app/Platform/Jobs/JobAdminRepository.php');
foreach(['formatDuration','Execution time','completed_at','failed_at'] as $needle){if(!str_contains((string)$controller.$repo,$needle)){fwrite(STDERR,"Missing jobs execution-time wiring: {$needle}\n");exit(1);}}
echo "Platform jobs execution-time static checks passed.\n";
// End of file.
