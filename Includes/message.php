<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/dbconnect.php';
require_once __DIR__ . '/auth.php';

$uid = isset($_SESSION['User_ID']) ? (int)$_SESSION['User_ID'] : 0;
$ajax = (isset($_POST['action']) || (isset($_GET['action']) && $_GET['action']==='fetch'));

if (!$conn || ($conn instanceof mysqli && $conn->connect_errno)) { if ($ajax){ header('Content-Type: application/json'); echo json_encode(['ok'=>false]); exit; } return; }
if ($ajax && $uid===0){ header('Content-Type: application/json'); echo json_encode(['ok'=>false]); exit; }

if (isset($_POST['action']) && $_POST['action']==='send') {
  header('Content-Type: application/json');
  $rid=(int)($_POST['receiver_id']??0);
  $msg=trim((string)($_POST['message']??''));
  if($rid>0 && $msg!==''){
    $s=$conn->prepare("INSERT INTO message (Sender_ID,Receiver_ID,Message,Message_Type) VALUES (?,?,?,'text')");
    $s->bind_param('iis',$uid,$rid,$msg); $s->execute();
    echo json_encode(['ok'=>true]); exit;
  }
  echo json_encode(['ok'=>false]); exit;
}

if (isset($_POST['action']) && $_POST['action']==='clear') {
  header('Content-Type: application/json');
  $rid=(int)($_POST['receiver_id']??0);
  if($rid>0){
    $s=$conn->prepare("DELETE FROM message WHERE (Sender_ID=? AND Receiver_ID=?) OR (Sender_ID=? AND Receiver_ID=?)");
    $s->bind_param('iiii',$uid,$rid,$rid,$uid); $s->execute();
    echo json_encode(['ok'=>true]); exit;
  }
  echo json_encode(['ok'=>false]); exit;
}

if (isset($_GET['action']) && $_GET['action']==='fetch' && isset($_GET['with'])) {
  header('Content-Type: application/json');
  $oid=(int)$_GET['with'];
  $q=$conn->prepare("SELECT Message_ID,Sender_ID,Receiver_ID,Message,Created_At FROM message WHERE (Sender_ID=? AND Receiver_ID=?) OR (Sender_ID=? AND Receiver_ID=?) ORDER BY Created_At ASC, Message_ID ASC");
  $q->bind_param('iiii',$uid,$oid,$oid,$uid); $q->execute();
  $r=$q->get_result(); $out=[]; while($m=$r->fetch_assoc()) $out[]=$m;
  echo json_encode($out); exit;
}

if ($uid===0) return;

$role='user';
$rt=$conn->prepare("SELECT TRIM(User_Type) AS r FROM user WHERE User_ID=?");
$rt->bind_param('i',$uid); $rt->execute();
if($row=$rt->get_result()->fetch_assoc()) $role=strtolower($row['r']);

$recipients=[];
if ($role==='admin') {
  $sql="SELECT User_ID, COALESCE(NULLIF(TRIM(Username),''), CONCAT('User #',User_ID)) AS Name, TRIM(User_Type) AS Role
        FROM user WHERE User_ID<>? ORDER BY LOWER(TRIM(User_Type))='guide' DESC, LOWER(TRIM(User_Type))='driver' DESC, LOWER(TRIM(User_Type))='user' DESC, Name";
  $p=$conn->prepare($sql); $p->bind_param('i',$uid); $p->execute();
  $g=$p->get_result(); while($u=$g->fetch_assoc()) if($u['User_ID']!=$uid) $recipients[]=$u;
} elseif ($role==='user') {
  $sql="SELECT DISTINCT u.User_ID, COALESCE(NULLIF(TRIM(u.Username),''), CONCAT('User #',u.User_ID)) AS Name, TRIM(u.User_Type) AS Role
        FROM bookings b
        LEFT JOIN guide g  ON b.Guide_ID=g.Guide_ID
        LEFT JOIN driver d ON b.Driver_ID=d.Driver_ID
        LEFT JOIN user u ON u.User_ID IN (g.User_ID,d.User_ID)
        WHERE b.User_ID=? AND u.User_ID IS NOT NULL
        UNION
        SELECT User_ID, COALESCE(NULLIF(TRIM(Username),''), CONCAT('User #',User_ID)) AS Name, TRIM(User_Type) AS Role
        FROM user WHERE LOWER(TRIM(User_Type))='admin'";
  $p=$conn->prepare($sql); $p->bind_param('i',$uid); $p->execute();
  $g=$p->get_result(); while($u=$g->fetch_assoc()) if($u['User_ID']!=$uid) $recipients[]=$u;
} elseif ($role==='guide') {
  $sql="SELECT DISTINCT u.User_ID, COALESCE(NULLIF(TRIM(u.Username),''), CONCAT('User #',u.User_ID)) AS Name, 'User' AS Role
        FROM bookings b
        JOIN guide g ON b.Guide_ID=g.Guide_ID
        JOIN user u ON u.User_ID=b.User_ID
        WHERE g.User_ID=?";
  $p=$conn->prepare($sql); $p->bind_param('i',$uid); $p->execute();
  $g=$p->get_result(); while($u=$g->fetch_assoc()) $recipients[]=$u;
} elseif ($role==='driver') {
  $sql="SELECT DISTINCT u.User_ID, COALESCE(NULLIF(TRIM(u.Username),''), CONCAT('User #',u.User_ID)) AS Name, 'User' AS Role
        FROM bookings b
        JOIN driver d ON b.Driver_ID=d.Driver_ID
        JOIN user u ON u.User_ID=b.User_ID
        WHERE d.User_ID=?";
  $p=$conn->prepare($sql); $p->bind_param('i',$uid); $p->execute();
  $g=$p->get_result(); while($u=$g->fetch_assoc()) $recipients[]=$u;
}

$base = (strpos($_SERVER['PHP_SELF'],'/Admin/')!==false || strpos($_SERVER['PHP_SELF'],'/Guide/')!==false) ? '../Includes/message.php' : 'Includes/message.php';
function e($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
?>
<div id="chatBtn" style="position:fixed;bottom:25px;right:25px;width:60px;height:60px;background:#00aeca;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;cursor:pointer;box-shadow:0 4px 8px rgba(0,0,0,.25);z-index:9999;">💬</div>

<div id="chatBox" style="position:fixed;bottom:100px;right:25px;width:340px;height:460px;background:#fff;border:1px solid #d7dee4;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.22);display:none;flex-direction:column;overflow:hidden;z-index:10000;">
  <div style="background:#00aeca;color:#fff;padding:10px 12px;font-weight:700;display:flex;align-items:center;justify-content:space-between;">
    <span>Messages</span>
    <button id="chatClose" type="button" style="background:transparent;border:none;color:#fff;font-size:18px;cursor:pointer;">✖</button>
  </div>
  <div style="padding:8px;border-bottom:1px solid #eef2f6;">
    <select id="chatTo" style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid #cbd5e1;">
      <option value="">Select user</option>
      <?php foreach($recipients as $r): ?>
        <option value="<?php echo (int)$r['User_ID']; ?>"><?php echo e($r['Name']).' ('.e($r['Role']).')'; ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div id="chatLog" style="flex:1;padding:10px;overflow-y:auto;font-size:14px;background:#f7f9fb;"></div>
  <form id="chatForm" style="display:flex;gap:6px;padding:8px;border-top:1px solid #eef2f6;">
    <input id="chatText" type="text" placeholder="Type a message..." autocomplete="off" style="flex:1;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;">
    <button style="background:#00aeca;color:#fff;border:none;padding:0 12px;border-radius:8px;cursor:pointer;">Send</button>
    <button id="chatClear" type="button" style="background:#f44336;color:#fff;border:none;padding:0 12px;border-radius:8px;cursor:pointer;">🗑</button>
  </form>
</div>

<script>
(function(){
  const btn=document.getElementById('chatBtn');
  const box=document.getElementById('chatBox');
  const close=document.getElementById('chatClose');
  const to=document.getElementById('chatTo');
  const log=document.getElementById('chatLog');
  const form=document.getElementById('chatForm');
  const text=document.getElementById('chatText');
  const clear=document.getElementById('chatClear');
  const base=<?php echo json_encode($base); ?>;
  const me=<?php echo (int)$uid; ?>;
  let current=null, poll=null;

  btn.onclick=()=>box.style.display='flex';
  close.onclick=()=>{ box.style.display='none'; };
  to.onchange=()=>{ current=to.value?parseInt(to.value,10):null; log.innerHTML=''; if(current){ load(); start(); } else { stop(); } };

  form.onsubmit=async (e)=>{
    e.preventDefault();
    const t=text.value.trim();
    if(!current||!t)return;
    const fd=new FormData();
    fd.append('action','send'); fd.append('receiver_id',current); fd.append('message',t);
    await fetch(base,{method:'POST',body:fd});
    text.value=''; load();
  };

  clear.onclick=async()=>{
    if(!current)return;
    if(!confirm('Clear this chat?'))return;
    const fd=new FormData();
    fd.append('action','clear'); fd.append('receiver_id',current);
    await fetch(base,{method:'POST',body:fd});
    log.innerHTML='';
  };

  async function load(){
    if(!current)return;
    const r=await fetch(base+'?action=fetch&with='+current,{cache:'no-store'});
    const d=await r.json();
    log.innerHTML='';
    d.forEach(m=>{
      const mine=(String(m.Sender_ID)===String(me));
      const row=document.createElement('div');
      row.style.display='flex';
      row.style.margin='6px 0';
      row.style.justifyContent=mine?'flex-end':'flex-start';
      const b=document.createElement('div');
      b.textContent=m.Message;
      b.style.padding='8px 10px';
      b.style.borderRadius='10px';
      b.style.maxWidth='80%';
      b.style.wordWrap='break-word';
      b.style.background=mine?'#e8f0fe':'#fff';
      b.style.border='1px solid #e5e7eb';
      row.appendChild(b);
      log.appendChild(row);
    });
    log.scrollTop=log.scrollHeight;
  }
  function start(){ stop(); poll=setInterval(load,3000); }
  function stop(){ if(poll){ clearInterval(poll); poll=null; } }

  window.openChatWith=function(id){ btn.click(); to.value=String(id); to.dispatchEvent(new Event('change')); };
})();
</script>
