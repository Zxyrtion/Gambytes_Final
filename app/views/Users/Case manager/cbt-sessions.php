<?php
require_once __DIR__ . '/../../../../includes/session_config.php';
require_once __DIR__ . '/../../../../includes/url_helper.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . url('app/views/auth/login.php')); exit(); }
require_once __DIR__ . '/../../../core/Database.php';
$db = new Database(); $conn = $db->connect();

$user_id = (int)$_SESSION['user_id'];
$uStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->bind_param('i', $user_id); $uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc(); $uStmt->close();

if (!$user || $user['role'] !== 'case_manager') {
    header("Location: " . url('app/views/auth/dashboard.php')); exit();
}
$full_name = $user['first_name'] . ' ' . $user['last_name'];

$booking_id = (int)($_GET['booking_id'] ?? 0);
if (!$booking_id) { header("Location: /GAMBYTES_Final/app/views/Users/Case manager/my-patients.php"); exit(); }

// Create CBT sessions table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS `cbt_session_progress` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `booking_id` INT(11) NOT NULL,
    `gambler_id` INT(11) NULL,
    `session_number` INT(11) NOT NULL,
    `status` ENUM('locked','unlocked','completed') DEFAULT 'locked',
    `unlocked_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `unlocked_by` INT(11) NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_session` (`booking_id`, `session_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Load booking + patient info
$bStmt = $conn->prepare(
    "SELECT br.*, u.id AS gambler_id, u.first_name, u.last_name
     FROM booking_record br
     LEFT JOIN users u ON LOWER(u.email) COLLATE utf8mb4_unicode_ci = LOWER(br.email) COLLATE utf8mb4_unicode_ci AND u.role = 'gambler'
     WHERE br.id = ?
     LIMIT 1"
);
$bStmt->bind_param('i', $booking_id); $bStmt->execute();
$booking = $bStmt->get_result()->fetch_assoc(); $bStmt->close();
if (!$booking) { header("Location: /GAMBYTES_Final/app/views/Users/Case manager/my-patients.php"); exit(); }

$gambler_id = (int)($booking['gambler_id'] ?? 0);
$patient_name = trim(($booking['first_name'] ?? '') . ' ' . ($booking['last_name'] ?? ''));
if (!$patient_name) $patient_name = htmlspecialchars($booking['name'] ?? 'Unknown');

// CBT Session definitions from the PDF — full content
$cbt_sessions = [
    1 => [
        'title' => 'Assessment',
        'description' => 'Learn about gambling patterns, consider gambling goals, and outline a path for moving forward with treatment.',
        'goals' => [
            'To learn more about your gambling patterns',
            'To consider your gambling goals',
            'To outline a path for moving forward with treatment'
        ],
        'content_html' => '
<p style="font-size:.88rem;color:#495057;margin-bottom:1rem">This session focuses on understanding your gambling history and setting goals for recovery. The patient will complete the following exercises.</p>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">Exercise #1 – Preferred Forms of Gambling</strong>
  <p style="font-size:.83rem;color:#495057;margin:.5rem 0 0">Patient lists their top 3 preferred forms of gambling (ranked by preference) and the age they began each. They also describe what they like about these types of gambling.</p>
</div>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">Advantages &amp; Disadvantages</strong>
  <p style="font-size:.83rem;color:#495057;margin:.5rem 0 0">Patient writes what they like about gambling (advantages), what they hate about gambling (disadvantages), and their reasons for wanting to stop.</p>
</div>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">Gambling Budget</strong>
  <p style="font-size:.83rem;color:#495057;margin:.5rem 0 0">Patient calculates their gross annual income, 2% of that income (estimated gambling budget), monthly budget, and actual amount spent on gambling last year. Research shows spending more than 2% of annual income on gambling is a sign of problem gambling.</p>
</div>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">Exercise #2 – South Oaks Gambling Screen (SOGS) &amp; NODS</strong>
  <p style="font-size:.83rem;color:#495057;margin:.5rem 0 0">Patient answers Yes/No to 20 screening questions about gambling behavior. Score: 0 = no problem, 1–4 = mild to moderate, 5–20 = significant problem. Also completes the 10-item NORC Diagnostic Screen (NODS).</p>
</div>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">Reasons for Gambling Checklist</strong>
  <p style="font-size:.83rem;color:#495057;margin:.5rem 0 0">Patient checks Always / Sometimes / Never for 12 reasons for gambling (e.g., excitement, escape, boredom, depression, habit).</p>
</div>
<div style="background:#e8f4fd;border-left:4px solid #0d6efd;border-radius:8px;padding:.75rem;margin-top:.5rem">
  <strong style="color:#0d6efd;font-size:.83rem"><i class="fas fa-home me-1"></i>Homework #1</strong>
  <p style="font-size:.82rem;color:#495057;margin:.4rem 0 0">Gambling Diary – Patient reconstructs a calendar for the past 30 days recording frequency of gambling episodes, outcome, and total won/lost per episode. <br><strong>Homework #2</strong> – Facing Financial Debt: Patient lists all creditors and amounts owed.</p>
</div>'
    ],
    2 => [
        'title' => 'Dealing With Consequences',
        'description' => 'Help you be honest with family about money owed, determine pressing debts, and deal with legal/work problems.',
        'goals' => [
            'To help you be honest with family about the money you owe',
            'To determine the most pressing debts and how to deal with them',
            'Dealing with legal problems created by gambling',
            'Dealing with work / employers'
        ],
        'content_html' => '
<p style="font-size:.88rem;color:#495057;margin-bottom:1rem">This session identifies the most common consequences of problem gambling and helps the patient develop a plan to address them.</p>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">Dealing with Debt</strong>
  <p style="font-size:.83rem;color:#495057;margin:.5rem 0 0">Covers the 4 categories of pressure debts: (1) debts to friends/relatives, (2) aggressively collected debts, (3) debts with mounting interest, (4) debts risking loss of car/home. Two options: Option 1 – pay a set amount to each creditor monthly; Option 2 – prioritize and postpone, negotiate reduced payments.</p>
</div>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">Dealing with Casino Debt</strong>
  <p style="font-size:.83rem;color:#495057;margin:.5rem 0 0">Contact casino collections immediately. Ask to be excluded via self-exclusion agreement. Request removal from all promotional mailing lists.</p>
</div>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">Bookies and Loan Sharks</strong>
  <p style="font-size:.83rem;color:#495057;margin:.5rem 0 0">Be humble and dignified. They often accept long-term payment plans. If threats continue or harm is done, contact police immediately. GA "Trusted Servants" have experience handling these situations.</p>
</div>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">Dealing with the IRS</strong>
  <p style="font-size:.83rem;color:#495057;margin:.5rem 0 0">Address delinquent taxes as soon as possible. IRS typically offers 36-month payment plans. Pathological gambling is a psychiatric condition — patient may qualify for special repayment programs.</p>
</div>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">Dealing with Family Members</strong>
  <p style="font-size:.83rem;color:#495057;margin:.5rem 0 0">Be honest about gambling and where money went. Invite family to GA or Gam-Anon. Family welfare always comes first — debt payments start only after household expenses are met.</p>
</div>
<div style="background:#e8f4fd;border-left:4px solid #0d6efd;border-radius:8px;padding:.75rem;margin-top:.5rem">
  <strong style="color:#0d6efd;font-size:.83rem"><i class="fas fa-home me-1"></i>Homework #3</strong>
  <p style="font-size:.82rem;color:#495057;margin:.4rem 0 0">Consequences Table – Patient fills in current problems and action plans for: Financial, Personal, Legal, Work/School, Friends, Family, Medical, Emotional/Psychological.</p>
</div>'
    ],
    3 => [
        'title' => 'Why It\'s So Hard to Stop',
        'description' => 'Learn about distorted thoughts about gambling, acknowledge problems erroneous thoughts can cause, and learn why superstitions aren\'t true.',
        'goals' => [
            'To learn about the distorted thoughts you have about gambling',
            'To acknowledge the problems erroneous thoughts can cause',
            'To learn why superstitions aren\'t true'
        ],
        'content_html' => '
<p style="font-size:.88rem;color:#495057;margin-bottom:1rem">This session helps the patient identify irrational thoughts about gambling and develop alternative rational thinking.</p>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">Distorted Thoughts</strong>
  <p style="font-size:.83rem;color:#495057;margin:.5rem 0 0">Our brains look for patterns — but this does not work for gambling. Each play is an independent event. The Gambler\'s Fallacy: "past results do not affect the probability of future events." Patient checks which distorted thoughts they have used: "I\'ll just play a little while," "One bet won\'t harm me," "I can win it back," "I can\'t lose on my birthday," etc.</p>
</div>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">Illusion of Control: Superstitions</strong>
  <p style="font-size:.83rem;color:#495057;margin:.5rem 0 0">The amount of skill in gambling is overestimated. Patient writes their personal superstitions about gambling and tries to provide evidence they can influence outcomes (they cannot). Key insight: "You have no control over chance and nothing you do will increase your chances of winning."</p>
</div>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">What Cognitive Distortions Lead To</strong>
  <p style="font-size:.83rem;color:#495057;margin:.5rem 0 0">Irrational thoughts → powerful feelings → gambling behavior. Patient maps their automatic thoughts to the feelings they create (e.g., "This time will be different" → Hope, optimism).</p>
</div>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">Developing Alternative Thoughts</strong>
  <p style="font-size:.83rem;color:#495057;margin:.5rem 0 0">Patient completes a 4-column chart: Automatic Thought → Alternative Thought → New Behavior → Outcome. Example: "I need to get away to the casino to relax" → "I can relax in other ways" → Go to gym / GA meeting → Not gambling when angry.</p>
</div>
<div style="background:#e8f4fd;border-left:4px solid #0d6efd;border-radius:8px;padding:.75rem;margin-top:.5rem">
  <strong style="color:#0d6efd;font-size:.83rem"><i class="fas fa-home me-1"></i>Homework #4</strong>
  <p style="font-size:.82rem;color:#495057;margin:.4rem 0 0">Patient fills out the 4-column chart for the last 3 occasions they went gambling, identifying automatic thoughts, alternative thoughts, new behaviors, and outcomes.</p>
</div>'
    ],
    4 => [
        'title' => 'Dealing With Urges and Triggers',
        'description' => 'Learn the difference between gambling urges and triggers, and learn ways to deal with gambling urges and triggers.',
        'goals' => [
            'To learn the difference between gambling urges and triggers',
            'To learn ways to deal with gambling urges and triggers'
        ],
        'content_html' => '
<p style="font-size:.88rem;color:#495057;margin-bottom:1rem">Urges are internal feelings of wanting to gamble. Triggers are external events or emotional reactions that create those urges. This session teaches 8 techniques to manage both.</p>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">Recognizing Triggers</strong>
  <p style="font-size:.83rem;color:#495057;margin:.5rem 0 0">Two types: (1) Internal – feelings of uncertainty, guilt, shame, depression, anger, anxiety; (2) External – objects, words, images, or situations associated with gambling (e.g., billboard, TV poker, freeway exit for racetrack). Patient describes their personal triggers.</p>
</div>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">8 Techniques for Dealing with Triggers</strong>
  <ul style="font-size:.83rem;color:#495057;margin:.5rem 0 0;padding-left:1.2rem">
    <li><strong>#1 Identification</strong> – Simply recognize you are having a trigger. First step in managing it.</li>
    <li><strong>#2 Positive Substitution</strong> – Replace gambling mental image with a healthy activity (fishing, golf). Actually do the substitute activity.</li>
    <li><strong>#3 Playing Out the Script</strong> – Mentally follow through the full gambling scenario to its real end: losing everything, shame in the parking lot, facing your spouse.</li>
    <li><strong>#4 Immediate Negative Conditioning</strong> – Connect the urge to gamble with your worst gambling memory so it automatically comes to mind.</li>
    <li><strong>#5 Postpone Gambling</strong> – Tell yourself you won\'t act for the next hour, 10 minutes, or even 1 minute. Break it into the smallest increment needed.</li>
    <li><strong>#6 Support</strong> – Call someone who has been through similar problems. Use religious/spiritual beliefs. Go to Gamblers Anonymous.</li>
    <li><strong>#7 Limiting Access to Gambling</strong> – Self-exclusion, cut off transportation, move away from casino, remove from marketing lists, spend less time with gambling friends.</li>
    <li><strong>#8 Limiting Access to Money</strong> – Cancel credit cards, limit ATM access, take only daily cash needed, have wages collected by spouse, arrange co-signing on checks.</li>
  </ul>
</div>
<div style="background:#e8f4fd;border-left:4px solid #0d6efd;border-radius:8px;padding:.75rem;margin-top:.5rem">
  <strong style="color:#0d6efd;font-size:.83rem"><i class="fas fa-home me-1"></i>Homework #5</strong>
  <p style="font-size:.82rem;color:#495057;margin:.4rem 0 0">Patient tries at least 3 of the 8 techniques and reports on how effective or ineffective each was.</p>
</div>'
    ],
    5 => [
        'title' => 'Lifestyle Changes',
        'description' => 'Consider issues in your life not directly related to gambling, identify strategies for dealing with them, and learn problem solving skills.',
        'goals' => [
            'To consider what issues you have in your life that aren\'t directly related to gambling',
            'To identify those issues and consider strategies for dealing with them',
            'To learn problem solving skills for dealing with the stress of daily life'
        ],
        'content_html' => '
<p style="font-size:.88rem;color:#495057;margin-bottom:1rem">As a problem gambler, certain responsibilities may have been neglected. This session addresses broader life issues and builds healthy coping skills.</p>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">Exercise #1 – Avoiding Avoidance</strong>
  <p style="font-size:.83rem;color:#495057;margin:.5rem 0 0">Gambling is often used to escape painful feelings (shame, guilt, helplessness, depression). Patient identifies what they were avoiding by gambling and the outcome of that avoidance. They also check which avoidance behaviors apply to them (drinking, eating, TV, internet, procrastinating, lying, video games, etc.).</p>
</div>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">Exercise #2 – Developing Ways to Cope</strong>
  <p style="font-size:.83rem;color:#495057;margin:.5rem 0 0">Patient rates how helpful each coping strategy would be (Not at All / Somewhat / Very): talking to a friend/therapist, journaling, meditation/yoga/breathing, regular exercise, attending GA meetings, planning activities, anger management, medications, getting more time for self.</p>
</div>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">Exercise #3 – Developing New Activities</strong>
  <p style="font-size:.83rem;color:#495057;margin:.5rem 0 0">Many gamblers struggle with boredom after stopping. Patient lists 6 past activities they enjoyed (that they gave up) and 6 new activities they want to try. Having structured activities builds new relationships and healthy commitments.</p>
</div>
<div style="background:#e8f4fd;border-left:4px solid #0d6efd;border-radius:8px;padding:.75rem;margin-top:.5rem">
  <strong style="color:#0d6efd;font-size:.83rem"><i class="fas fa-home me-1"></i>Focus on Wellness</strong>
  <p style="font-size:.82rem;color:#495057;margin:.4rem 0 0">Patient reviews wellness areas: Exercise (60 min/day target), Sleep (7 hours ideal — gamblers make worst decisions when sleep-deprived), Nutrition (improves mood, energy, self-esteem), Physical health maintenance, and Addressing other addictions that may emerge when gambling stops.</p>
</div>'
    ],
    6 => [
        'title' => 'Preventing Relapses',
        'description' => 'Learn about Gamblers Anonymous, experience different types of meetings, and select components that work for you.',
        'goals' => [
            'To learn about Gamblers Anonymous, what it is, and how it can help you',
            'To experience the different types of meetings available',
            'To select components of GA that you can feel most comfortable with'
        ],
        'content_html' => '
<p style="font-size:.88rem;color:#495057;margin-bottom:1rem">This final session covers the difference between a slip and a full relapse, and introduces Gamblers Anonymous as a long-term support tool.</p>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">Slip vs. Relapse</strong>
  <p style="font-size:.83rem;color:#495057;margin:.5rem 0 0">A <strong>slip</strong> is an isolated incident — the person feels guilt/remorse and wants to correct it. A <strong>relapse</strong> is giving up on recovery entirely. When a slip happens: expect problems to return, predict high-risk times, learn from triggers, spot ways to avoid risky situations next time.</p>
</div>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">Personal Emergency Reminder Sheet</strong>
  <p style="font-size:.83rem;color:#495057;margin:.5rem 0 0">Patient creates their emergency plan for high-risk situations: (1) Leave/change the situation, (2) Postpone decision 15 min — cravings are time-limited, (3) Challenge gambling thoughts, (4) Do something unrelated to gambling, (5) Remind self of successes, (6) Remind self of what they have to lose, (7) Remind self thinking becomes irrational, (8) Call emergency supporters (name + phone).</p>
</div>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">Gamblers Anonymous (GA)</strong>
  <p style="font-size:.83rem;color:#495057;margin:.5rem 0 0">Started in Los Angeles in 1957 — second oldest 12-step program. No dues. Research shows those who attend both therapy AND GA have better outcomes. GA offers: structure/support, reduction of shame/guilt, maintaining abstinence, spiritual basis for recovery, improved self-esteem. Patient reflects on past GA experience and attitude then vs. now.</p>
</div>
<div style="background:#fff8f0;border-left:4px solid #800000;border-radius:8px;padding:1rem;margin-bottom:1rem">
  <strong style="color:#800000;font-size:.9rem">Choosing a Sponsor</strong>
  <p style="font-size:.83rem;color:#495057;margin:.5rem 0 0">A sponsor should have several years of abstinence, not be struggling with major problems, be an active 12-step participant, be someone you can relate to and respect, and respect your confidentiality. Meet over coffee first to discuss how they work with sponsees.</p>
</div>
<div style="background:#e8f4fd;border-left:4px solid #0d6efd;border-radius:8px;padding:.75rem;margin-top:.5rem">
  <strong style="color:#0d6efd;font-size:.83rem"><i class="fas fa-info-circle me-1"></i>If I Experience a Lapse</strong>
  <p style="font-size:.82rem;color:#495057;margin:.4rem 0 0">(1) Get rid of gambling paraphernalia and leave the setting. (2) One slip does not have to become a full relapse — stop NOW. (3) Call someone for help. (4) Examine the lapse and identify triggers. (5) Set up a self-management plan. (6) Write down illogical thinking and causes of self-deception.</p>
</div>'
    ]
];

// Load current session progress
$progress = [];
$pStmt = $conn->prepare("SELECT * FROM cbt_session_progress WHERE booking_id = ? ORDER BY session_number");
$pStmt->bind_param('i', $booking_id);
$pStmt->execute();
$progressRows = $pStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pStmt->close();

foreach ($progressRows as $row) {
    $progress[$row['session_number']] = $row;
}

// Initialize sessions if not exists
for ($i = 1; $i <= 6; $i++) {
    if (!isset($progress[$i])) {
        $status = ($i === 1) ? 'unlocked' : 'locked';
        $insStmt = $conn->prepare("INSERT IGNORE INTO cbt_session_progress (booking_id, gambler_id, session_number, status) VALUES (?, ?, ?, ?)");
        $insStmt->bind_param('iiis', $booking_id, $gambler_id, $i, $status);
        $insStmt->execute();
        $insStmt->close();
        $progress[$i] = ['session_number' => $i, 'status' => $status, 'unlocked_at' => null, 'completed_at' => null, 'notes' => null];
    }
}

function getStatusBadge($status) {
    switch ($status) {
        case 'completed': return ['label' => 'Completed', 'bg' => '#d1e7dd', 'color' => '#0f5132', 'icon' => 'check-circle'];
        case 'unlocked':  return ['label' => 'Available', 'bg' => '#fff3cd', 'color' => '#664d03', 'icon' => 'unlock'];
        case 'locked':    return ['label' => 'Locked',    'bg' => '#f8d7da', 'color' => '#842029', 'icon' => 'lock'];
        default:          return ['label' => 'Unknown',   'bg' => '#e2e3e5', 'color' => '#41464b', 'icon' => 'question'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CBT Sessions &ndash; Gambytes</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/GAMBYTES_Final/public/style.css">
<style>
.main-content{margin-left:260px;flex:1;padding:2rem}
.fc-card{background:#fff;border-radius:16px;box-shadow:0 2px 15px rgba(0,0,0,.08);overflow:hidden;margin-bottom:1.5rem}
.fc-card-header{background:linear-gradient(135deg,#800000,#5c0000);color:#fff;padding:1rem 1.5rem;font-weight:700;font-size:1rem;display:flex;align-items:center;gap:.6rem}
.fc-card-body{padding:1.5rem}
.top-navbar{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.08);padding:1rem 1.5rem;margin-bottom:2rem;display:flex;justify-content:space-between;align-items:center}
.btn-maroon{background:linear-gradient(135deg,#800000,#5c0000);color:#fff;border:none;border-radius:10px;padding:.55rem 1.25rem;font-weight:700;font-size:.88rem;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem;text-decoration:none;transition:all .2s}
.btn-maroon:hover{opacity:.88;color:#fff}
.session-card{border:2px solid #e9ecef;border-radius:16px;padding:1.5rem;margin-bottom:1.25rem;background:#fff;transition:all .2s;position:relative}
.session-card:hover{box-shadow:0 6px 20px rgba(0,0,0,.1);transform:translateY(-2px)}
.session-number{position:absolute;top:-12px;left:1.5rem;background:linear-gradient(135deg,#800000,#5c0000);color:#fff;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.9rem;box-shadow:0 2px 8px rgba(128,0,0,.3)}
.info-box{padding:.6rem .85rem;background:#f8f9fa;border-radius:8px;border-left:3px solid #800000}
.info-box .lbl{font-size:.7rem;color:#6c757d;font-weight:600;text-transform:uppercase}
.info-box .val{font-weight:700;color:#212529;font-size:.88rem;margin-top:1px}
.session-content-panel{display:none;margin-top:1rem;padding:1rem;background:#fafafa;border-radius:10px;border:1px solid #e9ecef}
.session-content-panel.open{display:block}
.btn-outline-maroon{background:#fff;color:#800000;border:2px solid #800000;border-radius:10px;padding:.4rem .9rem;font-weight:700;font-size:.82rem;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;transition:all .2s}
.btn-outline-maroon:hover{background:#800000;color:#fff}
</style>
</head>
<body>
<div class="dashboard-container">

<div class="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo"><img src="/GAMBYTES_Final/public/images/Logo.png" alt="Logo"><span>Gambytes</span></div>
    <div class="sidebar-user">
      <div class="user-name"><i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($full_name) ?></div>
      <div class="user-role">Case Manager</div>
    </div>
  </div>
  <ul class="sidebar-menu">
    <li><a href="/GAMBYTES_Final/app/views/auth/dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
    <li><a href="/GAMBYTES_Final/app/views/Users/Case manager/my-patients.php"><i class="fas fa-users"></i> My Patients</a></li>
    <li><a href="#"><i class="fas fa-chart-line"></i> Treatment Progress</a></li>
    <li><a href="#"><i class="fas fa-calendar-alt"></i> Schedule</a></li>
    <li><a href="#"><i class="fas fa-file-medical"></i> Reports</a></li>
    <div class="menu-divider"></div>
    <li><a href="#"><i class="fas fa-user"></i> Profile</a></li>
    <li><a href="/GAMBYTES_Final/app/views/auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
  </ul>
</div>

<div class="main-content">
  <div class="top-navbar">
    <span style="font-weight:700;font-size:1rem;color:#800000">Case Manager Portal</span>
  </div>

  <div style="margin-bottom:1.75rem">
    <a href="/GAMBYTES_Final/app/views/Users/Case manager/patient-activities.php?booking_id=<?= $booking_id ?>" style="color:#800000;font-size:.88rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;margin-bottom:.5rem">
      <i class="fas fa-arrow-left"></i> Back to Patient Activities
    </a>
    <h1 style="color:#800000;font-size:1.8rem;font-weight:800;margin:0"><i class="fas fa-brain me-2"></i>CBT Sessions</h1>
    <p style="color:#6c757d;margin:.25rem 0 0">Cognitive Behavioral Therapy sessions for <?= htmlspecialchars($patient_name) ?></p>
  </div>

  <!-- Patient Info -->
  <div class="fc-card">
    <div class="fc-card-header"><i class="fas fa-user-injured"></i> Patient Information</div>
    <div class="fc-card-body">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem">
        <div class="info-box">
          <div class="lbl">Patient Name</div>
          <div class="val"><?= htmlspecialchars($patient_name) ?></div>
        </div>
        <div class="info-box">
          <div class="lbl">Email</div>
          <div class="val" style="font-size:.78rem"><?= htmlspecialchars($booking['email']) ?></div>
        </div>
        <div class="info-box">
          <div class="lbl">Program</div>
          <div class="val">6-Session CBT Program</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Sessions -->
  <div class="fc-card">
    <div class="fc-card-header"><i class="fas fa-list-ol"></i> Treatment Sessions</div>
    <div class="fc-card-body">
      <?php foreach ($cbt_sessions as $num => $session): 
        $prog = $progress[$num] ?? ['status' => 'locked'];
        $status = getStatusBadge($prog['status']);
        $cardBorder = $prog['status'] === 'completed' ? '#198754' : ($prog['status'] === 'unlocked' ? '#ffc107' : '#e9ecef');
      ?>
      <div class="session-card" style="border-color:<?= $cardBorder ?>">
        <div class="session-number"><?= $num ?></div>

        <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:.75rem;margin-bottom:.75rem">
          <div style="flex:1;padding-top:.25rem">
            <h4 style="color:#800000;font-weight:700;margin:0 0 .3rem;font-size:1.05rem">Session <?= $num ?>: <?= htmlspecialchars($session['title']) ?></h4>
            <p style="color:#6c757d;margin:0;font-size:.85rem"><?= htmlspecialchars($session['description']) ?></p>
            <ul style="margin:.4rem 0 0;padding-left:1.2rem;font-size:.8rem;color:#6c757d">
              <?php foreach ($session['goals'] as $g): ?><li><?= htmlspecialchars($g) ?></li><?php endforeach; ?>
            </ul>
          </div>

          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.5rem;min-width:160px">
            <span style="background:<?= $status['bg'] ?>;color:<?= $status['color'] ?>;padding:.3rem .8rem;border-radius:20px;font-size:.78rem;font-weight:700;display:inline-flex;align-items:center;gap:.35rem">
              <i class="fas fa-<?= $status['icon'] ?>"></i><?= $status['label'] ?>
            </span>

            <!-- View Content -->
            <button class="btn-outline-maroon" onclick="toggleContent(<?= $num ?>)" id="viewBtn<?= $num ?>">
              <i class="fas fa-eye" id="viewIcon<?= $num ?>"></i> View Content
            </button>

            <?php if ($prog['status'] !== 'locked'): ?>
            <!-- Send as Activity -->
            <button class="btn-maroon" style="font-size:.82rem;padding:.4rem .9rem;background:linear-gradient(135deg,#0d6efd,#0a58ca)"
              onclick="openSendModal(<?= $num ?>, '<?= addslashes($session['title']) ?>')">
              <i class="fas fa-paper-plane"></i> Send as Activity
            </button>
            <?php endif; ?>

            <?php if ($prog['status'] === 'locked' && $num > 1): ?>
            <button class="btn-maroon" style="font-size:.82rem;padding:.4rem .9rem" onclick="unlockSession(<?= $num ?>)">
              <i class="fas fa-unlock"></i> Unlock Session
            </button>
            <?php elseif ($prog['status'] === 'unlocked'): ?>
            <button onclick="markCompleted(<?= $num ?>)" style="background:#198754;color:#fff;border:none;border-radius:10px;padding:.4rem .9rem;font-weight:600;font-size:.82rem;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem">
              <i class="fas fa-check"></i> Mark Complete
            </button>
            <?php endif; ?>
          </div>
        </div>

        <!-- Expandable Content Panel -->
        <div class="session-content-panel" id="contentPanel<?= $num ?>">
          <?= $session['content_html'] ?>
        </div>

        <?php if ($prog['unlocked_at'] || $prog['completed_at']): ?>
        <div style="border-top:1px solid #e9ecef;padding-top:.6rem;margin-top:.75rem;font-size:.78rem;color:#6c757d;display:flex;gap:1.25rem;flex-wrap:wrap">
          <?php if ($prog['unlocked_at']): ?>
          <span><i class="fas fa-unlock me-1"></i>Unlocked: <?= date('M j, Y g:i A', strtotime($prog['unlocked_at'])) ?></span>
          <?php endif; ?>
          <?php if ($prog['completed_at']): ?>
          <span><i class="fas fa-check-circle me-1" style="color:#198754"></i>Completed: <?= date('M j, Y g:i A', strtotime($prog['completed_at'])) ?></span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div><!-- /main-content -->
</div><!-- /dashboard-container -->

<!-- ── Send as Activity Modal ── -->
<div class="modal fade" id="sendActivityModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="false">
  <div class="modal-dialog modal-md">
    <div class="modal-content" style="border-radius:16px;overflow:hidden">
      <div class="modal-header" style="background:linear-gradient(135deg,#0d6efd,#0a58ca);color:#fff;border:none">
        <h5 class="modal-title"><i class="fas fa-paper-plane me-2"></i>Send Session as Activity</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:1.5rem">
        <div id="sendActivityAlert" style="display:none" class="alert"></div>
        <form id="sendActivityForm" enctype="multipart/form-data">
          <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
          <input type="hidden" name="gambler_id" value="<?= $gambler_id ?>">
          <input type="hidden" name="session_number" id="modalSessionNum" value="">

          <div class="mb-3">
            <label class="form-label" style="font-weight:600;color:#343a40">Session</label>
            <input type="text" id="modalSessionTitle" class="form-control" readonly
              style="border-radius:10px;border:1.5px solid #dee2e6;background:#f8f9fa;font-weight:600">
            <input type="hidden" name="title" id="modalTitleHidden">
          </div>

          <div class="row g-3">
            <div class="col-6">
              <label class="form-label" style="font-weight:600;color:#343a40">Start Date <span style="color:#dc3545">*</span></label>
              <input type="date" name="open_date" id="modalOpenDate" class="form-control" required
                style="border-radius:10px;border:1.5px solid #dee2e6">
            </div>
            <div class="col-6">
              <label class="form-label" style="font-weight:600;color:#343a40">Due Date <span style="color:#dc3545">*</span></label>
              <input type="date" name="close_date" id="modalCloseDate" class="form-control" required
                style="border-radius:10px;border:1.5px solid #dee2e6">
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer" style="border:none;padding:1rem 1.5rem">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:10px">Cancel</button>
        <button type="button" id="sendActivityBtn" onclick="submitSendActivity()"
          style="background:linear-gradient(135deg,#0d6efd,#0a58ca);color:#fff;border:none;border-radius:10px;padding:.55rem 1.25rem;font-weight:700;font-size:.88rem;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem">
          <i class="fas fa-paper-plane"></i> Send Activity
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const BOOKING_ID = <?= $booking_id ?>;

// ── Toggle session content panel ─────────────────────────────────────────────
function toggleContent(num) {
  const panel = document.getElementById('contentPanel' + num);
  const icon  = document.getElementById('viewIcon' + num);
  const btn   = document.getElementById('viewBtn' + num);
  const open  = panel.classList.toggle('open');
  icon.className = open ? 'fas fa-eye-slash' : 'fas fa-eye';
  btn.innerHTML = open
    ? '<i class="fas fa-eye-slash" id="viewIcon' + num + '"></i> Hide Content'
    : '<i class="fas fa-eye" id="viewIcon' + num + '"></i> View Content';
}

// ── Unlock session ────────────────────────────────────────────────────────────
function unlockSession(sessionNum) {
  if (!confirm(`Unlock Session ${sessionNum} for this patient?`)) return;
  fetch('/GAMBYTES_Final/api/case_manager.php?action=unlock_cbt_session', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ booking_id: BOOKING_ID, session_number: sessionNum })
  }).then(r=>r.json()).then(d => {
    if (d.success) location.reload();
    else alert(d.message || 'Failed to unlock session.');
  }).catch(() => alert('Network error.'));
}

// ── Mark complete ─────────────────────────────────────────────────────────────
function markCompleted(sessionNum) {
  if (!confirm(`Mark Session ${sessionNum} as completed?`)) return;
  fetch('/GAMBYTES_Final/api/case_manager.php?action=complete_cbt_session', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ booking_id: BOOKING_ID, session_number: sessionNum })
  }).then(r=>r.json()).then(d => {
    if (d.success) location.reload();
    else alert(d.message || 'Failed to complete session.');
  }).catch(() => alert('Network error.'));
}

// ── Send as Activity modal ────────────────────────────────────────────────────
let _sendModalInstance = null;

function openSendModal(num, title) {
  document.getElementById('modalSessionNum').value   = num;
  document.getElementById('modalSessionTitle').value = `Session ${num}: ${title}`;
  document.getElementById('modalTitleHidden').value  = `Session ${num}: ${title}`;
  // Default dates: today → today+7
  const today = new Date();
  const due   = new Date(); due.setDate(due.getDate() + 7);
  document.getElementById('modalOpenDate').value  = today.toISOString().split('T')[0];
  document.getElementById('modalCloseDate').value = due.toISOString().split('T')[0];
  document.getElementById('sendActivityAlert').style.display = 'none';

  const modalEl = document.getElementById('sendActivityModal');
  // Reuse existing instance or create once
  _sendModalInstance = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: false });
  _sendModalInstance.show();
}

function submitSendActivity() {
  const alertEl = document.getElementById('sendActivityAlert');
  const btn     = document.getElementById('sendActivityBtn');
  const form    = document.getElementById('sendActivityForm');
  alertEl.style.display = 'none';

  const open  = form.querySelector('[name="open_date"]').value;
  const close = form.querySelector('[name="close_date"]').value;
  if (!open || !close) {
    alertEl.className = 'alert alert-danger';
    alertEl.textContent = 'Please fill in start and due dates.';
    alertEl.style.display = 'block'; return;
  }
  if (close < open) {
    alertEl.className = 'alert alert-danger';
    alertEl.textContent = 'Due date must be on or after start date.';
    alertEl.style.display = 'block'; return;
  }

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

  fetch('/GAMBYTES_Final/api/case_manager.php?action=create_cbt_activity', {
    method: 'POST',
    body: new FormData(form)
  }).then(r => {
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.text();
  }).then(text => {
    let d;
    try { d = JSON.parse(text); } catch(e) {
      alertEl.className = 'alert alert-danger';
      alertEl.textContent = 'Server error: ' + text.substring(0, 200);
      alertEl.style.display = 'block';
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Activity';
      return;
    }
    if (d.success) {
      alertEl.className = 'alert alert-success';
      alertEl.textContent = 'Activity sent successfully!';
      alertEl.style.display = 'block';
      setTimeout(() => {
        const modalEl = document.getElementById('sendActivityModal');
        const instance = bootstrap.Modal.getInstance(modalEl);
        if (instance) instance.hide();
      }, 1200);
    } else {
      alertEl.className = 'alert alert-danger';
      alertEl.textContent = d.message || 'Failed to send activity.';
      alertEl.style.display = 'block';
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Activity';
    }
  }).catch(err => {
    alertEl.className = 'alert alert-danger';
    alertEl.textContent = 'Error: ' + err.message;
    alertEl.style.display = 'block';
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Activity';
  });
}

// Clean up backdrop and body classes when modal fully closes
document.addEventListener('DOMContentLoaded', function () {
  const modalEl = document.getElementById('sendActivityModal');
  modalEl.addEventListener('hidden.bs.modal', function () {
    // Remove any lingering backdrop
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
    // Reset the send button
    const btn = document.getElementById('sendActivityBtn');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Activity';
  });
});
</script>
</body>
</html>