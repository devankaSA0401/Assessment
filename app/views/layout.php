<?php $title = $title ?? 'DNET Vendor Assessment'; $user=current_user(); ?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> · DNET Assessment</title><link rel="stylesheet" href="<?=url('assets/css/app.css')?>"></head><body>
<div class="app-shell">
<header class="topbar"><a class="brand" href="<?=url('dashboard')?>"><span class="brand-mark">D</span><span>DNET <b>Assessment</b></span></a><?php if($user): ?><div class="user-menu"><span><?=e($user['name'])?></span><a href="<?=url('logout')?>">Logout</a></div><?php endif; ?></header>
<main class="container"><?php if($msg=flash('success')): ?><div class="alert success"><?=e($msg)?></div><?php endif; ?><?php if($msg=flash('error')): ?><div class="alert danger"><?=e($msg)?></div><?php endif; ?><?= $content ?></main>
</div><script src="<?=url('assets/js/app.js')?>"></script></body></html>
