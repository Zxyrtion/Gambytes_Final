<?php
require_once __DIR__ . "/../includes/session_config.php";
require_once __DIR__ . "/../app/core/Database.php";
header("Content-Type: application/json");
if (!isset($_SESSION["user_id"])) { http_response_code(401); echo json_encode(["success"=>false,"message"=>"Unauthorized"]); exit(); }
$db = new Database(); $conn = $db->connect();

// ── GET: lookup family member by name ────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "GET" && ($_GET["action"] ?? "") === "lookup_family") {
    $fn = trim($_GET["first"] ?? "");
    $ln = trim($_GET["last"]  ?? "");
    if (!$fn || !$ln) { echo json_encode(["found"=>false]); exit(); }
    $s = $conn->prepare("SELECT id, first_name, last_name FROM users WHERE role='family' AND LOWER(first_name)=LOWER(?) AND LOWER(last_name)=LOWER(?) LIMIT 1");
    $s->bind_param("ss",$fn,$ln);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if ($row) {
        echo json_encode(["found"=>true,"name"=>htmlspecialchars($row["first_name"]." ".$row["last_name"]),"id"=>(int)$row["id"]]);
    } else {
        echo json_encode(["found"=>false]);
    }
    exit();
}
$uid = (int)$_SESSION["user_id"];
$bid = (int)($_POST["booking_id"] ?? 0);
if (!$bid) { echo json_encode(["success"=>false,"message"=>"Invalid booking"]); exit(); }
$s = $conn->prepare("SELECT role FROM users WHERE id=?"); $s->bind_param("i",$uid); $s->execute(); $u = $s->get_result()->fetch_assoc(); $s->close();
if (!$u || $u["role"] !== "gambler") { echo json_encode(["success"=>false,"message"=>"Permission denied"]); exit(); }
$chk = $conn->prepare("SELECT id FROM contract_documents WHERE user_id=? AND booking_id=? LIMIT 1"); $chk->bind_param("ii",$uid,$bid); $chk->execute(); $ex = $chk->get_result()->fetch_assoc(); $chk->close();
if ($ex) { echo json_encode(["success"=>false,"message"=>"Already submitted"]); exit(); }
$se = json_encode($_POST, JSON_UNESCAPED_UNICODE);
$fe = json_encode($_POST, JSON_UNESCAPED_UNICODE);
$pv = json_encode($_POST, JSON_UNESCAPED_UNICODE);
$sig1 = $_POST["self_exclusion_sig"] ?? "";
$sig2 = $_POST["family_exclusion_sig"] ?? "";
$sig2b = $_POST["family_exclusion_counter_sig"] ?? "";
$sig3 = $_POST["player_verification_sig"] ?? "";
$status = "submitted";
$conn->query("ALTER TABLE contract_documents ADD COLUMN IF NOT EXISTS family_exclusion_counter_sig LONGTEXT NULL AFTER family_exclusion_sig");
$conn->query("ALTER TABLE contract_documents ADD COLUMN IF NOT EXISTS family_member_id INT(11) NULL AFTER booking_id");

// Look up the family member's user account by name entered in the form
$feFirst = trim($_POST["fe_app_first_name"] ?? "");
$feLast  = trim($_POST["fe_app_last_name"]  ?? "");
$family_member_id = null;
if ($feFirst && $feLast) {
    $fmS = $conn->prepare("SELECT id FROM users WHERE role='family' AND LOWER(first_name)=LOWER(?) AND LOWER(last_name)=LOWER(?) LIMIT 1");
    $fmS->bind_param("ss", $feFirst, $feLast);
    $fmS->execute();
    $fmRow = $fmS->get_result()->fetch_assoc();
    $fmS->close();
    if ($fmRow) { $family_member_id = (int)$fmRow["id"]; }
}

$ins = $conn->prepare("INSERT INTO contract_documents (user_id,booking_id,family_member_id,self_exclusion_data,family_exclusion_data,player_verification_data,self_exclusion_sig,family_exclusion_sig,family_exclusion_counter_sig,player_verification_sig,status) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
$ins->bind_param("iiissssssss",$uid,$bid,$family_member_id,$se,$fe,$pv,$sig1,$sig2,$sig2b,$sig3,$status);
if ($ins->execute()) { $ins->close(); echo json_encode(["success"=>true,"message"=>"Submitted successfully"]); } else { $ins->close(); echo json_encode(["success"=>false,"message"=>"DB error: " . $conn->error]); }
