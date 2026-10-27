<?php
require_once __DIR__.'/config/config.php'; require_login(); require_once __DIR__.'/includes/mailer.php';
$uid=(int)$_SESSION['user_id']; $errors=[];
$stmt=$conn->prepare('SELECT id,animal_code,name FROM animals WHERE owner_id=? ORDER BY animal_code'); $stmt->bind_param('i',$uid); $stmt->execute(); $animals=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf(); $title=trim($_POST['title']??''); $due=$_POST['due_date']??''; $animal_id=($_POST['animal_id']??'')!==''?(int)$_POST['animal_id']:null; $notes=trim($_POST['notes']??'');
 if($title===''||$due==='')$errors[]='Task title and due date are required.';
 if($due!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$due))$errors[]='Enter a valid due date.';
 if($animal_id){$check=$conn->prepare('SELECT id FROM animals WHERE id=? AND owner_id=?');$check->bind_param('ii',$animal_id,$uid);$check->execute();if(!$check->get_result()->num_rows)$errors[]='Choose livestock from your own records.';}
 if(!$errors){$stmt=$conn->prepare('INSERT INTO reminders(owner_id,animal_id,title,due_date,notes) VALUES(?,?,?,?,?)');$stmt->bind_param('iisss',$uid,$animal_id,$title,$due,$notes);$stmt->execute();$rid=(int)$conn->insert_id;
  $userStmt=$conn->prepare('SELECT full_name,email FROM users WHERE id=?');$userStmt->bind_param('i',$uid);$userStmt->execute();$user=$userStmt->get_result()->fetch_assoc();
  $date=date('d M Y',strtotime($due)); $html='<h2>HerdSignal ZW reminder saved</h2><p>Hello '.e($user['full_name']).',</p><p>Your reminder <strong>'.e($title).'</strong> is scheduled for <strong>'.e($date).'</strong>.</p>'.($notes!==''?'<p>Notes: '.nl2br(e($notes)).'</p>':'').'<p>Sign in to HerdSignal ZW to review your livestock tasks.</p>';
  $result=herdsignal_send_email((string)($user['email']??''),(string)$user['full_name'],'HerdSignal ZW: Reminder saved - '.$title,$html);
  if($result['sent']){flash('Reminder saved and confirmation email sent.');}else{flash('Reminder saved. Email was not sent: '.$result['error'],'info');}
  header('Location: reminders.php');exit;
 }
}
$page_title='Create reminder'; require __DIR__.'/includes/header.php'; ?>
<?php foreach($errors as $err):?><div class="alert error"><?=e($err)?></div><?php endforeach;?>
<div class="form-card"><div class="panel-heading"><div><h3>Schedule a task</h3><p>Save a task and send an email confirmation to your registered address.</p></div></div><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><div class="form-grid"><div class="field full"><label>Task title *</label><input name="title" required maxlength="140" placeholder="e.g. Vaccination or dipping"></div><div class="field"><label>Due date *</label><input type="date" name="due_date" required value="<?=e($_POST['due_date']??date('Y-m-d'))?>"></div><div class="field"><label>Related livestock (optional)</label><select name="animal_id"><option value="">General farm task</option><?php foreach($animals as $a):?><option value="<?=$a['id']?>"><?=e($a['animal_code'].' '.($a['name']?:''))?></option><?php endforeach;?></select></div><div class="field full"><label>Notes</label><textarea name="notes" placeholder="Optional notes"><?=e($_POST['notes']??'')?></textarea></div></div><div class="form-actions"><button class="btn btn-primary">Save reminder</button><a class="btn btn-light" href="reminders.php">Cancel</a></div></form></div><?php require __DIR__.'/includes/footer.php'; ?>