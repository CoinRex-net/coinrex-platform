<?php
/** PRO/Expert weekly check-in state and mutations. */
require_once __DIR__ . '/_bootstrap.php';
try {
    // Keep this hot path lightweight. apiResolveAuthorizedUserId() calls
    // getCurrentUser(), which performs another auth query plus a full level
    // synchronization. The streak endpoint only needs the authenticated ID and
    // the user's persisted level, so verify the session once and fetch once.
    if(!isLoggedIn()) apiErrorResponse(401,'Authentication required.');
    $user_id=(int)($_SESSION['user_id']??0);
    $method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
    if($method==='POST' && !validateAppCsrfToken((string)($_POST['csrf_token']??'')))
        apiErrorResponse(403,'Invalid CSRF token.');
    if(session_status()===PHP_SESSION_ACTIVE) session_write_close();

    $db=getDBConnection();
    ensureProWeeklyStreakSchema($db); $user=getUserById($user_id);
    if(!$user || !proWeeklyStreakUserIsEligible($user))
        apiErrorResponse(403,'PRO or Expert access is required.');
    if($method==='GET') {
        apiSuccessResponse(['state'=>proWeeklyStreakGetState((int)$user_id,$db),
            'balance'=>number_format(getRewardLedgerBalance((int)$user_id,'available',$db),8,'.','')]);
    }
    if($method!=='POST') apiErrorResponse(405,'Method not allowed.');
    $action=strtolower(trim((string)($_POST['action']??'')));
    if($action==='checkin') {
        $result=proWeeklyStreakCheckIn((int)$user_id,$db);
        apiSuccessResponse([
            'message'=>!empty($result['box_unlocked'])
                ?'Day 7 complete! Your mystery box is ready.'
                :'Day '.(int)($result['state']['current_day']??0).' check-in complete.',
            'reward'=>number_format((float)$result['reward'],8,'.',''),
            'box_unlocked'=>!empty($result['box_unlocked']),'state'=>$result['state'],
            'balance'=>number_format(getRewardLedgerBalance((int)$user_id,'available',$db),8,'.','')]);
    }
    if($action==='claim_box') {
        $result=proWeeklyStreakClaimBox((int)$user_id,$db);
        apiSuccessResponse(['message'=>'Mystery box opened! You won '.(int)$result['reward'].' $REX.',
            'reward'=>number_format((float)$result['reward'],8,'.',''),'state'=>$result['state'],
            'balance'=>number_format(getRewardLedgerBalance((int)$user_id,'available',$db),8,'.','')]);
    }
    throw new InvalidArgumentException('Valid action is required.');
} catch(DomainException $e) {
    apiErrorResponse(403,$e->getMessage());
} catch(Throwable $e) {
    apiErrorResponse(422,$e->getMessage());
}
