<?php
// Run from Windows Task Scheduler every hour: php.exe C:\\wamp64\\www\\herdsignal\\send_due_reminders.php
require_once __DIR__.'/config/config.php'; require_once __DIR__.'/includes/mailer.php';
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
$today=date('Y-m-d');
$sql="SELECT r.id,r.title,r.due_date,r.notes,u.full_name,u.email FROM reminders r JOIN users u ON u.id=r.owner_id WHERE r.status='Pending' AND r.due_date<=? AND r.email_sent_at IS NULL AND u.email IS NOT NULL AND u.email<>'' ORDER BY r.due_date ASC LIMIT 100";
$stmt=$conn->prepare($sql);$stmt->bind_param('s',$today);$stmt->execute();$rows=$stmt->get_result();$sent=0;$failed=0;
while($r=$rows->fetch_assoc()){
 $date=date('d M Y',strtotime($r['due_date']));$html='<h2>HerdSignal ZW task reminder</h2><p>Hello '.e($r['full_name']).',</p><p>Your task <strong>'.e($r['title']).'</strong> is due on <strong>'.e($date).'</strong>.</p>'.($r['notes']?'<p>Notes: '.nl2br(e($r['notes'])).'</p>':'').'<p>Please sign in to HerdSignal ZW to update the task when complete.</p>';
 $result=herdsignal_send_email($r['email'],$r['full_name'],'HerdSignal ZW: Task due - '.$r['title'],$html);
 if($result['sent']){$up=$conn->prepare('UPDATE reminders SET email_sent_at=NOW(),email_last_error=NULL WHERE id=?');$up->bind_param('i',$r['id']);$up->execute();$sent++;}
 else {$err=substr($result['error'],0,255);$up=$conn->prepare('UPDATE reminders SET email_last_error=? WHERE id=?');$up->bind_param('si',$err,$r['id']);$up->execute();$failed++;}
}
echo "HerdSignal due reminder run complete. Sent: $sent; failed/skipped: $failed\n";
