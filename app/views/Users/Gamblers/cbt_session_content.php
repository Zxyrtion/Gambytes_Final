<?php
/**
 * CBT Session Workbook Content
 * Included by view-activity.php — $cbtSession is available from parent scope
 */
if ($cbtSession === 1): ?>

<!-- SESSION 1: ASSESSMENT -->
<div class="cbt-intro">
  <strong>Goals:</strong>
  <ul>
    <li>To learn more about your gambling patterns</li>
    <li>To consider your gambling goals</li>
    <li>To outline a path for moving forward with treatment</li>
  </ul>
</div>

<div class="cbt-section">
  <div class="cbt-section-title">Exercise #1 — Your Gambling Patterns</div>
  <p class="cbt-q">List your top three preferred forms of gambling (rank in order of preference):</p>
  <div class="row g-3 mb-3">
    <div class="col-md-7"><label class="cbt-label">Most Preferred</label><input type="text" class="form-control cbt-input" name="cbt_gambling_1" placeholder="e.g. Slot machines"></div>
    <div class="col-md-3"><label class="cbt-label">Age I began</label><input type="number" class="form-control cbt-input" name="cbt_age_1" placeholder="e.g. 20" min="1" max="99"></div>
  </div>
  <div class="row g-3 mb-3">
    <div class="col-md-7"><label class="cbt-label">Second</label><input type="text" class="form-control cbt-input" name="cbt_gambling_2" placeholder="e.g. Card games"></div>
    <div class="col-md-3"><label class="cbt-label">Age I began</label><input type="number" class="form-control cbt-input" name="cbt_age_2" placeholder="e.g. 22" min="1" max="99"></div>
  </div>
  <div class="row g-3 mb-4">
    <div class="col-md-7"><label class="cbt-label">Third</label><input type="text" class="form-control cbt-input" name="cbt_gambling_3" placeholder="e.g. Sports betting"></div>
    <div class="col-md-3"><label class="cbt-label">Age I began</label><input type="number" class="form-control cbt-input" name="cbt_age_3" placeholder="e.g. 25" min="1" max="99"></div>
  </div>
  <label class="cbt-label">What do you like about these types of gambling?</label>
  <textarea class="form-control cbt-input" name="cbt_likes" rows="3" placeholder="Write your answer here..."></textarea>
</div>

<div class="cbt-section">
  <div class="cbt-section-title">Advantages &amp; Disadvantages</div>
  <label class="cbt-label">What I like about gambling (Advantages)</label>
  <textarea class="form-control cbt-input mb-3" name="cbt_advantages" rows="3" placeholder="List the advantages..."></textarea>
  <label class="cbt-label">What I hate about gambling (Disadvantages)</label>
  <textarea class="form-control cbt-input mb-3" name="cbt_disadvantages" rows="3" placeholder="List the disadvantages..."></textarea>
  <label class="cbt-label">Reasons I Want to Stop Gambling</label>
  <textarea class="form-control cbt-input" name="cbt_reasons_stop" rows="3" placeholder="List your reasons..."></textarea>
</div>

<div class="cbt-section">
  <div class="cbt-section-title">Gambling Budget</div>
  <p class="cbt-note">Research shows that spending more than 2% of annual income on gambling may indicate a problem.</p>
  <div class="row g-3">
    <div class="col-md-6"><label class="cbt-label">A. Gross Annual Income (estimated)</label><input type="text" class="form-control cbt-input" name="cbt_income" placeholder="e.g. ₱500,000"></div>
    <div class="col-md-6"><label class="cbt-label">B. 2% of Annual Income (gambling budget/year)</label><input type="text" class="form-control cbt-input" name="cbt_budget_year" placeholder="e.g. ₱10,000"></div>
    <div class="col-md-6"><label class="cbt-label">C. Estimated gambling budget per month</label><input type="text" class="form-control cbt-input" name="cbt_budget_month" placeholder="e.g. ₱833"></div>
    <div class="col-md-6"><label class="cbt-label">D. Actual amount spent on gambling last year</label><input type="text" class="form-control cbt-input" name="cbt_actual_spent" placeholder="e.g. ₱80,000"></div>
  </div>
</div>

<div class="cbt-section">
  <div class="cbt-section-title">Exercise #2 — Reasons for Gambling</div>
  <p class="cbt-q">Check the box that most applies to you for each reason:</p>
  <div class="table-responsive">
    <table class="table table-bordered cbt-table">
      <thead><tr><th>Reason for Gambling</th><th class="text-center">Always</th><th class="text-center">Sometimes</th><th class="text-center">Never</th></tr></thead>
      <tbody>
        <?php $reasons = ['To provide excitement','To make money quickly','To feel like a big shot','To be more social because I felt shy','To not think about problems','To feel more powerful','To numb my feelings','To avoid people','To not feel bored','To get rid of feelings of depression or loneliness','To feel pleasure or to be entertained','Out of habit']; ?>
        <?php foreach ($reasons as $i => $r): ?>
        <tr>
          <td><?php echo htmlspecialchars($r); ?></td>
          <td class="text-center"><input type="radio" name="cbt_reason_<?php echo $i; ?>" value="Always"></td>
          <td class="text-center"><input type="radio" name="cbt_reason_<?php echo $i; ?>" value="Sometimes"></td>
          <td class="text-center"><input type="radio" name="cbt_reason_<?php echo $i; ?>" value="Never"></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($cbtSession === 2): ?>

<!-- SESSION 2: DEALING WITH CONSEQUENCES -->
<div class="cbt-intro">
  <strong>Goals:</strong>
  <ul>
    <li>To be honest with your family about the money you owe</li>
    <li>To determine the most pressing debts and how to deal with them</li>
    <li>To deal with legal problems created by gambling</li>
    <li>To deal with work / employer issues</li>
  </ul>
</div>

<div class="cbt-section">
  <div class="cbt-section-title">Homework #3 — Consequences of Gambling</div>
  <p class="cbt-q">For each area, describe the current problem and what you plan to do about it:</p>
  <?php $areas = ['Financial','Personal','Legal','Work / School','Friends','Family','Medical','Emotional / Psychological']; ?>
  <?php foreach ($areas as $area): $key = strtolower(str_replace([' ','/','-',' '], ['_','_','_','_'], $area)); ?>
  <div class="mb-4">
    <div class="cbt-area-label"><?php echo $area; ?></div>
    <div class="row g-2 mt-1">
      <div class="col-md-6">
        <label class="cbt-label">Current problem I have to deal with</label>
        <textarea class="form-control cbt-input" name="cbt_prob_<?php echo $key; ?>" rows="2" placeholder="Describe the problem..."></textarea>
      </div>
      <div class="col-md-6">
        <label class="cbt-label">What am I going to do to address this?</label>
        <textarea class="form-control cbt-input" name="cbt_plan_<?php echo $key; ?>" rows="2" placeholder="Describe your plan..."></textarea>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php elseif ($cbtSession === 3): ?>

<!-- SESSION 3: WHY IT'S SO HARD TO STOP -->
<div class="cbt-intro">
  <strong>Goals:</strong>
  <ul>
    <li>To learn about the distorted thoughts you have about gambling</li>
    <li>To acknowledge the problems erroneous thoughts can cause</li>
    <li>To learn why superstitions aren't true</li>
  </ul>
</div>

<div class="cbt-section">
  <div class="cbt-section-title">My Gambling Superstitions</div>
  <p class="cbt-q">What are your superstitions about gambling? Provide evidence that they can influence the outcome:</p>
  <div class="table-responsive">
    <table class="table table-bordered cbt-table">
      <thead><tr><th>My superstitions about gambling</th><th>Evidence</th></tr></thead>
      <tbody>
        <?php for ($i = 1; $i <= 3; $i++): ?>
        <tr>
          <td><textarea class="form-control cbt-input border-0 bg-transparent" name="cbt_superstition_<?php echo $i; ?>" rows="2" placeholder="Superstition #<?php echo $i; ?>..."></textarea></td>
          <td><textarea class="form-control cbt-input border-0 bg-transparent" name="cbt_evidence_<?php echo $i; ?>" rows="2" placeholder="Evidence..."></textarea></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="cbt-section">
  <div class="cbt-section-title">Distorted Thoughts Checklist</div>
  <p class="cbt-q">Check all the thoughts you have used while gambling or planning to gamble:</p>
  <?php $thoughts = ["I'll just play for a little while.","I deserve to gamble.","One bet won't harm me.","I might actually win this time.","And, how can I win if I don't play?","Gambling is an easy way to earn money.","My gambling is under control, I've just had a lot of bad luck recently.","I'm smart; I have a system to beat the odds.","Gambling will be the solution to my problems.","I will pay it back.","Gambling makes me feel better.","Someday I'll score a really big win.","I can win it back.","I can't lose on my birthday.","I am smarter than the other gamblers."]; ?>
  <div class="row g-2">
    <?php foreach ($thoughts as $i => $t): ?>
    <div class="col-md-6">
      <div class="form-check cbt-check">
        <input class="form-check-input" type="checkbox" name="cbt_thought_<?php echo $i; ?>" value="<?php echo htmlspecialchars($t); ?>" id="thought_<?php echo $i; ?>">
        <label class="form-check-label" for="thought_<?php echo $i; ?>"><?php echo htmlspecialchars($t); ?></label>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="cbt-section">
  <div class="cbt-section-title">Homework #4 — Developing Alternative Thoughts</div>
  <p class="cbt-q">For the last 3 occasions where you went gambling, fill in alternative thoughts and new behaviors:</p>
  <div class="table-responsive">
    <table class="table table-bordered cbt-table">
      <thead><tr><th>Automatic Thoughts</th><th>Alternative Thoughts</th><th>New Behavior</th><th>Outcome</th></tr></thead>
      <tbody>
        <?php for ($i = 1; $i <= 3; $i++): ?>
        <tr>
          <td><textarea class="form-control cbt-input border-0 bg-transparent" name="cbt_auto_<?php echo $i; ?>" rows="2" placeholder="Automatic thought..."></textarea></td>
          <td><textarea class="form-control cbt-input border-0 bg-transparent" name="cbt_alt_<?php echo $i; ?>" rows="2" placeholder="Alternative thought..."></textarea></td>
          <td><textarea class="form-control cbt-input border-0 bg-transparent" name="cbt_behavior_<?php echo $i; ?>" rows="2" placeholder="New behavior..."></textarea></td>
          <td><textarea class="form-control cbt-input border-0 bg-transparent" name="cbt_outcome_<?php echo $i; ?>" rows="2" placeholder="Outcome..."></textarea></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($cbtSession === 4): ?>

<!-- SESSION 4: DEALING WITH URGES AND TRIGGERS -->
<div class="cbt-intro">
  <strong>Goals:</strong>
  <ul>
    <li>To learn the difference between gambling urges and triggers</li>
    <li>To learn ways to deal with gambling urges and triggers</li>
  </ul>
</div>

<div class="cbt-section">
  <div class="cbt-section-title">Internal Triggers</div>
  <p class="cbt-q">Can you think of a recent situation that triggered uncomfortable feelings and led to an urge to gamble? Describe it below:</p>
  <textarea class="form-control cbt-input" name="cbt_internal_trigger" rows="4" placeholder="Describe the situation..."></textarea>
</div>

<div class="cbt-section">
  <div class="cbt-section-title">External Triggers</div>
  <p class="cbt-q">Can you think of something you experienced, saw or heard recently that triggered an urge to gamble?</p>
  <textarea class="form-control cbt-input mb-3" name="cbt_external_trigger" rows="3" placeholder="Describe the external trigger..."></textarea>
  <label class="cbt-label">Of the two kinds of triggers, which leads you to gamble? How have you dealt with it?</label>
  <textarea class="form-control cbt-input" name="cbt_trigger_response" rows="3" placeholder="Your response..."></textarea>
</div>

<div class="cbt-section">
  <div class="cbt-section-title">Technique #2 — Positive Substitution</div>
  <label class="cbt-label">What can you substitute for gambling when you experience a trigger?</label>
  <textarea class="form-control cbt-input" name="cbt_substitution" rows="3" placeholder="e.g. Go to the gym, call a friend, go for a walk..."></textarea>
</div>

<div class="cbt-section">
  <div class="cbt-section-title">Technique #3 — Playing Out the Script</div>
  <label class="cbt-label">Please write out what would realistically happen if you gamble:</label>
  <textarea class="form-control cbt-input" name="cbt_script" rows="4" placeholder="Describe the realistic outcome step by step..."></textarea>
</div>

<div class="cbt-section">
  <div class="cbt-section-title">Technique #4 — Worst Experiences</div>
  <label class="cbt-label">What are the "worst experiences" from gambling that you can remember?</label>
  <textarea class="form-control cbt-input" name="cbt_worst" rows="3" placeholder="Describe your worst gambling experiences..."></textarea>
</div>

<div class="cbt-section">
  <div class="cbt-section-title">Homework #5 — Techniques to Try</div>
  <p class="cbt-q">Try out at least three of the techniques and report on how effective or ineffective they are:</p>
  <textarea class="form-control cbt-input" name="cbt_techniques_report" rows="4" placeholder="Report on the techniques you tried..."></textarea>
</div>

<?php elseif ($cbtSession === 5): ?>

<!-- SESSION 5: LIFESTYLE CHANGES -->
<div class="cbt-intro">
  <strong>Goals:</strong>
  <ul>
    <li>To consider issues in your life not directly related to gambling</li>
    <li>To identify those issues and consider strategies for dealing with them</li>
    <li>To learn problem solving skills for dealing with daily stress</li>
  </ul>
</div>

<div class="cbt-section">
  <div class="cbt-section-title">Exercise #1 — Avoiding Avoidance</div>
  <label class="cbt-label">What were you avoiding by gambling, and what was the outcome?</label>
  <div class="table-responsive mb-3">
    <table class="table table-bordered cbt-table">
      <thead><tr><th>What I was avoiding</th><th>Outcome of avoiding</th></tr></thead>
      <tbody>
        <?php for ($i = 1; $i <= 4; $i++): ?>
        <tr>
          <td><input type="text" class="form-control cbt-input border-0 bg-transparent" name="cbt_avoiding_<?php echo $i; ?>" placeholder="What I was avoiding..."></td>
          <td><input type="text" class="form-control cbt-input border-0 bg-transparent" name="cbt_avoid_outcome_<?php echo $i; ?>" placeholder="Outcome..."></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="cbt-section">
  <div class="cbt-section-title">Exercise #2 — Coping Strategies</div>
  <p class="cbt-q">How helpful do you think each of these strategies would be for you?</p>
  <div class="table-responsive">
    <table class="table table-bordered cbt-table">
      <thead><tr><th>Strategy</th><th class="text-center">Not at All</th><th class="text-center">Somewhat</th><th class="text-center">Very</th></tr></thead>
      <tbody>
        <?php $strategies = ['Talking to a friend, family member, or therapist','Writing, keeping a journal or diary','Learning to relax through meditation, yoga, or breathing','Getting regular exercise','Attending Gamblers Anonymous meetings','Planning activities, setting goals','Learning anger management','Taking medications','Getting more time for myself']; ?>
        <?php foreach ($strategies as $i => $s): ?>
        <tr>
          <td><?php echo htmlspecialchars($s); ?></td>
          <td class="text-center"><input type="radio" name="cbt_strategy_<?php echo $i; ?>" value="Not at All"></td>
          <td class="text-center"><input type="radio" name="cbt_strategy_<?php echo $i; ?>" value="Somewhat"></td>
          <td class="text-center"><input type="radio" name="cbt_strategy_<?php echo $i; ?>" value="Very"></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="cbt-section">
  <div class="cbt-section-title">Exercise #3 — Developing New Activities</div>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="cbt-label">Past Activities That I Enjoyed</label>
      <?php for ($i = 1; $i <= 6; $i++): ?>
      <input type="text" class="form-control cbt-input mb-2" name="cbt_past_activity_<?php echo $i; ?>" placeholder="<?php echo $i; ?>. ">
      <?php endfor; ?>
    </div>
    <div class="col-md-6">
      <label class="cbt-label">New Activities That I Can Do</label>
      <?php for ($i = 1; $i <= 6; $i++): ?>
      <input type="text" class="form-control cbt-input mb-2" name="cbt_new_activity_<?php echo $i; ?>" placeholder="<?php echo $i; ?>. ">
      <?php endfor; ?>
    </div>
  </div>
</div>

<?php elseif ($cbtSession === 6): ?>

<!-- SESSION 6: PREVENTING RELAPSES -->
<div class="cbt-intro">
  <strong>Goals:</strong>
  <ul>
    <li>To understand the difference between a slip and a full relapse</li>
    <li>To create a personal emergency plan for high-risk situations</li>
    <li>To identify your relapse triggers and how to avoid them</li>
  </ul>
</div>

<div class="cbt-section">
  <div class="cbt-section-title">Relapse Prevention Plan</div>
  <p class="cbt-q">Describe past relapses and how to avoid them in the future:</p>
  <div class="table-responsive mb-3">
    <table class="table table-bordered cbt-table">
      <thead><tr><th>Description of relapse to gambling</th><th>How to avoid this from happening again</th></tr></thead>
      <tbody>
        <?php for ($i = 1; $i <= 4; $i++): ?>
        <tr>
          <td><textarea class="form-control cbt-input border-0 bg-transparent" name="cbt_relapse_desc_<?php echo $i; ?>" rows="2" placeholder="Describe the relapse situation..."></textarea></td>
          <td><textarea class="form-control cbt-input border-0 bg-transparent" name="cbt_relapse_avoid_<?php echo $i; ?>" rows="2" placeholder="How to avoid it..."></textarea></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="cbt-section">
  <div class="cbt-section-title">Personal Emergency Reminder Sheet</div>
  <p class="cbt-note">If I encounter a high-risk situation, I will follow these steps:</p>
  <ol class="cbt-steps">
    <li>I will leave or change the situation</li>
    <li>I will put off the decision to gamble for 15 minutes — cravings are time-limited</li>
    <li>I will challenge my thoughts about gambling (Do I really need to gamble? No.)</li>
    <li>I will think of and do something unrelated to gambling</li>
    <li>I will remind myself of my successes</li>
    <li>I will remind myself of all the things I have to lose by gambling</li>
    <li>I will remind myself that my thinking becomes irrational with respect to gambling</li>
    <li>I will call my emergency supporters</li>
  </ol>
  <label class="cbt-label mt-3">My Emergency Supporters</label>
  <div class="row g-3">
    <?php for ($i = 1; $i <= 3; $i++): ?>
    <div class="col-md-6"><input type="text" class="form-control cbt-input" name="cbt_supporter_name_<?php echo $i; ?>" placeholder="Name #<?php echo $i; ?>"></div>
    <div class="col-md-6"><input type="text" class="form-control cbt-input" name="cbt_supporter_phone_<?php echo $i; ?>" placeholder="Phone #<?php echo $i; ?>"></div>
    <?php endfor; ?>
  </div>
</div>

<div class="cbt-section">
  <div class="cbt-section-title">If I Experience a Lapse</div>
  <label class="cbt-label">Reflect on a recent lapse — what triggered it and what will you do differently?</label>
  <textarea class="form-control cbt-input" name="cbt_lapse_reflection" rows="4" placeholder="Describe the lapse and your plan going forward..."></textarea>
</div>

<?php endif; ?>
