<?php
// Health status chip: colour and word together, never colour alone (CORE-05, wireframe p6).
// Expects $status — a health_status value from the database: thriving, stable, needs_attention or sick.
?>
<span class="badge badge-<?= e(str_replace('_', '-', $status)) ?>"><?= e(health_label($status)) ?></span>
