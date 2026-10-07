<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$controller=(new ReflectionClass(App\Http\Controllers\Tenant\SocialFrontController::class))->newInstanceWithoutConstructor();
$timing=new ReflectionMethod($controller,'submissionTiming');
function timingCheck(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
function rejected(array $input): void {
    global $controller,$timing;
    try { $timing->invoke($controller,$input); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException('Invalid timing accepted: '.json_encode($input));
}
$GLOBALS['artsfolio_user_timezone']='America/New_York';
foreach (['', 'garbage', '2000-01-01T00:00', ['malformed']] as $local) {
    $before=time(); [$action,$utc]=$timing->invoke($controller,['publish_action'=>'post_now','scheduled_local'=>$local]);
    $stamp=(new DateTimeImmutable($utc,new DateTimeZone('UTC')))->getTimestamp();
    timingCheck($action==='post_now' && $stamp >= $before && $stamp <= time(),'Immediate posting must ignore schedule and use UTC now');
}
foreach ([[],['publish_action'=>'unknown'],['publish_action'=>[]],['publish_action'=>'schedule'],['publish_action'=>'schedule','scheduled_local'=>[]]] as $input) rejected($input);
foreach (['2000-01-01T00:00','2099-02-30T12:00','2099-03-08T02:30','2099-11-01T01:30','not-a-date'] as $local) rejected(['publish_action'=>'schedule','scheduled_local'=>$local]);
[$action,$utc]=$timing->invoke($controller,['publish_action'=>'schedule','scheduled_local'=>'2099-07-01T12:00']);
timingCheck($utc==='2099-07-01 16:00:00','DST timezone conversion');
[$action,$utc]=$timing->invoke($controller,['action'=>'schedule','scheduled_local'=>'2099-01-01T12:00']);
timingCheck($utc==='2099-01-01 17:00:00','Legacy explicit action and winter timezone conversion');
$GLOBALS['artsfolio_user_timezone']='Asia/Kathmandu';
[, $utc]=$timing->invoke($controller,['publish_action'=>'schedule','scheduled_local'=>'2099-01-01T12:00']);
timingCheck($utc==='2099-01-01 06:15:00','Fractional timezone offset');
$GLOBALS['artsfolio_user_timezone']='+05:30';
[, $utc]=$timing->invoke($controller,['publish_action'=>'schedule','scheduled_local'=>'2099-01-01T12:00']);
timingCheck($utc==='2099-01-01 06:30:00','Fixed offset without transitions');
$GLOBALS['artsfolio_user_timezone']='UTC';
rejected(['publish_action'=>'schedule','scheduled_local'=>gmdate('Y-m-d\TH:i')]);
$html=App\Http\View\ErrorPage::status(422,'Choose a valid, unambiguous future schedule date and time.');
timingCheck(!str_contains($html,'Could not create site') && str_contains($html,'Please check your submission.'),'422 copy must not mention creating a site');
echo "Social post timing: immediate, scheduled, missing/invalid mode, future, DST gap/fold and timezone checks passed.\n";
