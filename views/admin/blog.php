<?php use MediaPitch\Core\Csrf; ?>
<section class="panel">
  <div class="panel-head"><div><h2>Articles</h2><p class="muted">Create and manage editorial blog content.</p></div><a class="primary-button" href="<?= e(url('admin/blog/new')) ?>">New article</a></div>
  <?php if (!$posts): ?><p class="muted">No blog posts yet.</p><?php else: ?>
  <div class="table-wrap"><table class="data-table"><thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Publish date</th><th>Author</th><th></th></tr></thead><tbody>
  <?php foreach($posts as $post): ?><tr><td><strong><?= e($post['title']) ?></strong><small>/blog/<?= e($post['slug']) ?></small></td><td><?= e($post['category_name'] ?? '—') ?></td><td><span class="badge"><?= e($post['status']) ?></span></td><td><?= e($post['published_at'] ?? '—') ?></td><td><?= e($post['author_name'] ?? '—') ?></td><td><a href="<?= e(url('admin/blog/' . (int)$post['id'] . '/edit')) ?>">Edit</a> · <form method="post" action="<?= e(url('admin/blog/'.(int)$post['id'].'/delete')) ?>" style="display:inline" onsubmit="return confirm('Permanently delete this article? This cannot be undone.')"><?= Csrf::field() ?><button type="submit" class="text-link" style="border:0;background:none;padding:0;cursor:pointer">Delete</button></form></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>
