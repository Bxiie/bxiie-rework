<?php

declare(strict_types=1);
$root=dirname(__DIR__,2);
$checks=[
 'app/Platform/Analytics/AnalyticsRecorder.php'=>['class AnalyticsRecorder','HTTP_CF_IPCITY'],
 'app/Http/AppKernel.php'=>['AnalyticsRecorder'],
 'app/Platform/Analytics/AnalyticsLocationResolver.php'=>['background_lookup_pending'],
 'app/Platform/Analytics/AnalyticsRollupService.php'=>['analytics_rollups_hourly','analytics_rollups_daily'],
 'scripts/workers/run_once.php'=>["case 'analytics.rollup'",'AnalyticsRollupJobHandler'],
 'scripts/test/http_smoke.sh'=>['X-ArtsFolio-Test-Probe: http-smoke'],
 'app/Http/Controllers/Api/TenantMeController.php'=>['isTrustedLocalSmokeProbe','127.0.0.1'],
];
foreach($checks as $file=>$needles){$text=file_get_contents($root.'/'.$file);foreach($needles as $needle){if(!str_contains((string)$text,$needle)){fwrite(STDERR,"Missing {$needle} in {$file}\n");exit(1);}}}
$home=file_get_contents($root.'/app/Http/Controllers/Tenant/HomeController.php');
if(str_contains((string)$home,'new \App\Platform\Analytics\AnalyticsLocationResolver')){fwrite(STDERR,"HomeController still performs location resolution.\n");exit(1);}
$resolver=file_get_contents($root.'/app/Platform/Analytics/AnalyticsLocationResolver.php');
if(str_contains((string)$resolver,'file_get_contents(') || str_contains((string)$resolver,'information_schema')){fwrite(STDERR,"Location resolver still performs network/schema work.\n");exit(1);}
echo "Phase 3 analytics static checks passed.\n";
// End of file.
