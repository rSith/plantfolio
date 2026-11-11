<?php
// AI care guide on the Plant Profile (P05), under the plant's name: five short points an AI wrote for the plant's
// species, saved once per species in care_guides (new feature, 2026-11-11, UI half). Layout from Ranshitha's sketch:
// a compact list, "Light: …", above the action buttons.
// Expects:
//   $plant     — the plants row (id, nickname)
//   $is_owner  — true for the plant's owner: only the owner may regenerate or add a species
//   $state     — 'ready' (a saved guide), 'generating' (none saved yet: the AI is writing it), 'offline' (the AI
//                service did not answer), 'invalid' (its answer could not be read) or 'no-species'
//   $guide     — for 'ready': the decoded guide_json, one sentence or two per key of care_guide_sections()
//   $guide_row — for 'ready': the care_guides row (created_at)
//   $species   — the species name the guide is for
// Stage B: the 'generating' list is replaced by the real guide when care-guide.js gets it from api/care-guide.php.
// Every AI sentence is printed with e(), like any text a person typed.

$plant_id  = (int) $plant['id'];
$retry_url = url('plant.php?id=' . $plant_id . '#care-guide');
// Stage F: Regenerate shows the Generating state. Stage B: a POST form with a CSRF token, owner only.
$regen_url = url('plant.php?id=' . $plant_id . '&guide=generating#care-guide');

// The three things that can go wrong, each with a friendly title, what it means, and what to do next
$problems = [
    'offline' => [
        'title' => "The care guide can't be written right now",
        'text'  => 'The AI helper is not running at the moment. Please try again later.',
    ],
    'invalid' => [
        'title' => 'The AI gave an answer we could not read',
        'text'  => 'Nothing was saved. Trying again usually works.',
    ],
    'no-species' => [
        'title' => 'No species yet',
        'text'  => $is_owner
            ? 'Add the species of ' . $plant['nickname'] . ' to get an AI care guide.'
            : 'The owner has not added the species of this plant yet.',
    ],
];
?>
<section class="ai-guide" id="care-guide" aria-labelledby="ai-guide-title">
  <?php // A small label, so it is always clear that an AI wrote this ?>
  <h2 class="ai-guide-title" id="ai-guide-title"><?= icon('sparkles') ?> AI care guide</h2>

  <?php if ($state === 'ready' || $state === 'generating'): ?>
    <?php if ($state === 'generating'): ?>
      <p class="ai-guide-status" role="status">
        <span class="spinner" aria-hidden="true"></span>
        <span><strong>Generating care guide…</strong> This can take a minute or two.</span>
      </p>
    <?php endif; ?>

    <?php // The same five points in both states: while generating, grey bars stand where the text will appear ?>
    <ul class="ai-guide-list"<?= $state === 'generating' ? ' aria-hidden="true"' : '' ?>>
      <?php foreach (care_guide_sections() as $key => $section): ?>
        <li class="ai-guide-item">
          <span class="ai-guide-icon"><?= icon($section['icon']) ?></span>
          <?php if ($state === 'ready'): ?>
            <p><strong><?= e($section['label']) ?>:</strong> <?= e($guide[$key]) ?></p>
          <?php else: ?>
            <p><strong><?= e($section['label']) ?></strong><span class="ai-guide-skeleton"><span></span><span></span></span></p>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php if ($state === 'ready'): ?>
      <div class="ai-guide-foot">
        <p class="ai-guide-note">AI-generated guidance. May not be fully accurate.</p>
        <?php if ($is_owner): ?>
          <a class="ai-guide-regen" href="<?= e($regen_url) ?>"><?= icon('refresh-cw') ?> Regenerate</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  <?php else: ?>
    <?php $problem = $problems[$state]; ?>
    <div class="ai-guide-problem" role="status">
      <span class="ai-guide-icon"><?= icon($state === 'no-species' ? 'leaf' : 'triangle-alert') ?></span>
      <div>
        <p class="ai-guide-problem-title"><?= e($problem['title']) ?></p>
        <p class="ai-guide-problem-text"><?= e($problem['text']) ?></p>
        <?php if ($state === 'no-species'): ?>
          <?php if ($is_owner): ?>
            <a class="btn btn-secondary btn-sm" href="<?= e(url('plant-form.php?id=' . $plant_id . '#species')) ?>"><?= icon('pencil') ?> Add species</a>
          <?php endif; ?>
        <?php else: ?>
          <a class="btn btn-secondary btn-sm" href="<?= e($retry_url) ?>"><?= icon('refresh-cw') ?> Try again</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</section>
