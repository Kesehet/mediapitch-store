<?php use MediaPitch\Core\Csrf; $u=$editUser??[]; ?>
<div class="two-col">
  <div class="panel">
    <div class="panel-head"><div><h2>Users</h2><p>Manage CMS access and roles.</p></div></div>
    <table class="data-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach($users as $user):?><tr><td><?= e($user['name']) ?></td><td><?= e($user['email']) ?></td><td><span class="badge"><?= e(str_replace('_',' ',$user['role'])) ?></span></td><td><?= !empty($user['active'])?'Active':'Inactive' ?></td><td><a href="<?= e(url('admin/users?edit='.(int)$user['id'])) ?>">Edit</a></td></tr><?php endforeach;?>
    <?php if(!$users):?><tr><td colspan="5" class="empty">No users yet.</td></tr><?php endif;?>
    </tbody></table>
  </div>
  <div class="panel">
    <h2><?= !empty($u['id'])?'Edit user':'Add user' ?></h2>
    <form method="post" action="<?= e(url('admin/users/save')) ?>" class="stack-form">
      <?= Csrf::field() ?><?php if(!empty($u['id'])):?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><?php endif;?>
      <label>Name<input name="name" required value="<?= e($u['name']??'') ?>"></label>
      <label>Email<input type="email" name="email" required value="<?= e($u['email']??'') ?>"></label>
      <label>Role<select name="role"><option value="administrator" <?= ($u['role']??'')==='administrator'?'selected':'' ?>>Administrator</option><option value="editor" <?= ($u['role']??'')==='editor'?'selected':'' ?>>Editor</option><option value="writer" <?= ($u['role']??'writer')==='writer'?'selected':'' ?>>Writer</option><option value="seo_manager" <?= ($u['role']??'')==='seo_manager'?'selected':'' ?>>SEO Manager</option></select></label>
      <label>Password <?= !empty($u['id'])?'<small>Leave blank to keep current password</small>':'' ?><input type="password" name="password" <?= empty($u['id'])?'required':'' ?> minlength="8" autocomplete="new-password"></label>
      <label class="check"><input type="checkbox" name="active" value="1" <?= !isset($u['active'])||!empty($u['active'])?'checked':'' ?>> Active</label>
      <div class="form-actions"><?php if(!empty($u['id'])):?><a class="secondary-button" href="<?= e(url('admin/users')) ?>">Cancel</a><?php endif;?><button class="primary-button" type="submit">Save user</button></div>
    </form>
  </div>
</div>
