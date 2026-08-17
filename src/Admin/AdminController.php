<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Core\View;
use MediaPitch\Repositories\AdminRepository;
use MediaPitch\Repositories\ContentRepository;
use MediaPitch\Repositories\MediaRepository;
use MediaPitch\Repositories\RedirectRepository;
use MediaPitch\Repositories\UserRepository;
use MediaPitch\Services\ProductAdminActions;
use MediaPitch\Services\ProductAuthoring;
use Throwable;

final class AdminController
{
    public function __construct(private readonly AdminRepository $repo)
    {
    }

    public function handle(string $method, string $path): bool
    {
        if (!str_starts_with($path, '/admin')) return false;

        if ($path === '/admin/login') {
            if ($method === 'GET') {
                if (Auth::check()) { $this->redirect('/admin'); }
                View::render('admin/login', ['pageTitle'=>'Admin Login','error'=>$this->flash('error')], 'admin/auth-layout');
                return true;
            }
            if ($method === 'POST') {
                $this->requireCsrf();
                if (Auth::attempt((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''))) {
                    $this->redirect('/admin');
                }
                $message = Auth::loginBlocked()
                    ? 'Too many failed sign-in attempts. Try again in about ' . max(1, (int)ceil(Auth::retryAfter()/60)) . ' minute(s).'
                    : 'Invalid email or password.';
                $this->setFlash('error', $message);
                $this->redirect('/admin/login');
            }
        }

        if ($path === '/admin/logout' && $method === 'POST') {
            $this->requireCsrf();
            Auth::logout();
            $this->redirect('/admin/login');
        }

        $this->requireLogin();
        $mediaItems = null;
        $media = static function () use (&$mediaItems): array {
            return $mediaItems ??= (new MediaRepository())->all();
        };

        if ($path === '/admin' && $method === 'GET') {
            View::render('admin/dashboard', array_merge($this->repo->dashboard(), $this->common('Dashboard')), 'admin/layout');
            return true;
        }

        if ($path === '/admin/account' && $method === 'GET') {
            View::render('admin/account', $this->common('My Account'), 'admin/layout');
            return true;
        }
        if ($path === '/admin/account/password' && $method === 'POST') {
            $this->requireCsrf();
            try {
                $user=Auth::user();
                Auth::changePassword(
                    (int)$user['id'],
                    (string)($_POST['current_password']??''),
                    (string)($_POST['new_password']??''),
                    (string)($_POST['new_password_confirmation']??'')
                );
                $this->setFlash('success','Password changed successfully.');
            } catch (Throwable $e) {
                $this->setFlash('error',$e->getMessage());
            }
            $this->redirect('/admin/account');
        }

        if (str_starts_with($path, '/admin/users')) {
            if (!Auth::isAdministrator()) { http_response_code(403); exit('Forbidden'); }
            $usersRepo=new UserRepository();
            if ($path === '/admin/users' && $method === 'GET') {
                $editId=isset($_GET['edit'])?(int)$_GET['edit']:null;
                View::render('admin/users', array_merge([
                    'users'=>$usersRepo->all(),
                    'editUser'=>$usersRepo->find($editId),
                ],$this->common($editId?'Edit User':'Users')), 'admin/layout');
                return true;
            }
            if ($path === '/admin/users/save' && $method === 'POST') {
                $this->requireCsrf();
                try {
                    $current=Auth::user();
                    $usersRepo->save($_POST,!empty($_POST['id'])?(int)$_POST['id']:null,(int)$current['id']);
                    $this->setFlash('success','User saved.');
                } catch (Throwable $e) {
                    $this->setFlash('error','User could not be saved: '.$e->getMessage());
                }
                $this->redirect('/admin/users');
            }
        }

        if ($path === '/admin/categories' && $method === 'GET') {
            View::render('admin/categories', array_merge([
                'categories'=>$this->repo->categories(),
                'categoryOptions'=>$this->repo->categoryOptions(),
                'mediaItems'=>$media(),
            ], $this->common('Categories')), 'admin/layout');
            return true;
        }
        if ($path === '/admin/categories/save' && $method === 'POST') {
            $this->requireEditor(); $this->requireCsrf();
            try {
                $this->repo->saveCategory($_POST, !empty($_POST['id']) ? (int)$_POST['id'] : null);
                $this->setFlash('success','Category saved.');
            } catch (Throwable $e) {
                $this->setFlash('error','Category could not be saved: ' . $e->getMessage());
            }
            $this->redirect('/admin/categories');
        }

        if ($path === '/admin/brands' && $method === 'GET') {
            $editId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
            View::render('admin/brands', array_merge([
                'brands'=>$this->repo->brands(),
                'brand'=>$this->repo->brand($editId),
                'mediaItems'=>$media(),
            ], $this->common($editId ? 'Edit Brand' : 'Brands')), 'admin/layout');
            return true;
        }
        if ($path === '/admin/brands/save' && $method === 'POST') {
            $this->requireEditor(); $this->requireCsrf();
            try {
                $website=trim((string)($_POST['website_url']??''));
                $logo=trim((string)($_POST['logo_url']??''));
                if($website!=='' && !filter_var($website,FILTER_VALIDATE_URL)) throw new \InvalidArgumentException('Brand website URL is invalid.');
                if($logo!=='' && !filter_var($logo,FILTER_VALIDATE_URL)) throw new \InvalidArgumentException('Brand logo URL is invalid.');
                $this->repo->saveBrand($_POST, !empty($_POST['id']) ? (int)$_POST['id'] : null);
                $this->setFlash('success','Brand saved.');
            } catch (Throwable $e) {
                $this->setFlash('error','Brand could not be saved: ' . $e->getMessage());
            }
            $this->redirect('/admin/brands');
        }

        if ($path === '/admin/specifications' && $method === 'GET') {
            $editId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
            View::render('admin/specifications', array_merge([
                'definitions'=>$this->repo->specificationDefinitions(),
                'definition'=>$this->repo->specificationDefinition($editId),
                'categories'=>$this->repo->categoryOptions(),
            ], $this->common($editId ? 'Edit Specification' : 'Specifications')), 'admin/layout');
            return true;
        }
        if ($path === '/admin/specifications/save' && $method === 'POST') {
            $this->requireEditor(); $this->requireCsrf();
            try {
                $this->repo->saveSpecificationDefinition($_POST, !empty($_POST['id']) ? (int)$_POST['id'] : null);
                $this->setFlash('success','Specification saved.');
            } catch (Throwable $e) {
                $this->setFlash('error','Specification could not be saved: ' . $e->getMessage());
            }
            $this->redirect('/admin/specifications');
        }

        if ($path === '/admin/products' && $method === 'GET') {
            View::render('admin/products', array_merge(['products'=>$this->repo->products()], $this->common('Products')), 'admin/layout');
            return true;
        }
        if ($method === 'GET' && ($path === '/admin/products/new' || preg_match('#^/admin/products/(\d+)/edit$#', $path, $m))) {
            $id = isset($m[1]) ? (int)$m[1] : null;
            View::render('admin/product-form', array_merge([
                'product'=>$this->repo->product($id),
                'categories'=>$this->repo->categoryOptions(),
                'brands'=>$this->repo->brands(),
                'specDefinitions'=>$this->repo->specificationDefinitions(),
                'specValues'=>$this->repo->productSpecificationValues($id),
                'mediaItems'=>$media(),
            ], $this->common($id ? 'Edit Product' : 'Add Product')), 'admin/layout');
            return true;
        }
        if ($path === '/admin/products/save' && $method === 'POST') {
            $this->requireEditor(); $this->requireCsrf();
            $existingId=!empty($_POST['id']) ? (int)$_POST['id'] : null;
            $oldProduct=$existingId?$this->repo->product($existingId):null;
            try {
                $data=(new ProductAuthoring())->prepare($_POST,$existingId);
                $id=$this->repo->saveProduct($data,$existingId);
                if($oldProduct && !empty($oldProduct['slug']) && $oldProduct['slug']!==$data['slug']){
                    (new RedirectRepository())->upsert('/product/'.$oldProduct['slug'],'/product/'.$data['slug']);
                }
                $this->setFlash('success','Product saved.');
                $this->redirect('/admin/products/' . $id . '/edit');
            } catch (Throwable $e) {
                $this->setFlash('error','Product could not be saved: ' . $e->getMessage());
                $this->redirect($existingId ? '/admin/products/'.$existingId.'/edit' : '/admin/products/new');
            }
        }
        if ($method === 'POST' && preg_match('#^/admin/products/(\d+)/(archive|restore|duplicate)$#',$path,$m)) {
            $this->requireEditor(); $this->requireCsrf();
            $id=(int)$m[1]; $action=$m[2];
            try{
                $actions=new ProductAdminActions();
                if($action==='archive'){
                    $actions->archive($id); $this->setFlash('success','Product archived.'); $this->redirect('/admin/products');
                }
                if($action==='restore'){
                    $actions->restore($id); $this->setFlash('success','Product restored.'); $this->redirect('/admin/products');
                }
                $newId=$actions->duplicate($id);
                $this->setFlash('success','Product duplicated as an inactive draft.');
                $this->redirect('/admin/products/'.$newId.'/edit');
            }catch(Throwable $e){
                $this->setFlash('error','Product action failed: '.$e->getMessage());
                $this->redirect('/admin/products');
            }
        }

        $contentRepo = new ContentRepository();
        if ($path === '/admin/blog' && $method === 'GET') {
            View::render('admin/blog', array_merge(['posts'=>$contentRepo->adminPosts()], $this->common('Blog')), 'admin/layout');
            return true;
        }
        if ($method === 'GET' && ($path === '/admin/blog/new' || preg_match('#^/admin/blog/(\d+)/edit$#', $path, $m))) {
            $id=isset($m[1]) ? (int)$m[1] : null;
            View::render('admin/blog-form', array_merge([
                'post'=>$contentRepo->adminPost($id),
                'categories'=>$this->repo->categoryOptions(),
                'mediaItems'=>$media(),
            ], $this->common($id ? 'Edit Article' : 'New Article')), 'admin/layout');
            return true;
        }
        if ($path === '/admin/blog/save' && $method === 'POST') {
            $this->requireCsrf();
            $status=(string)($_POST['status'] ?? 'draft');
            if (in_array($status,['published','scheduled'],true) && !Auth::canPublish()) {
                http_response_code(403); echo 'You do not have permission to publish or schedule.'; return true;
            }
            $existingId=!empty($_POST['id'])?(int)$_POST['id']:null;
            $oldPost=$existingId?$contentRepo->adminPost($existingId):null;
            try {
                $id=$contentRepo->savePost($_POST,(int)Auth::user()['id'],$existingId);
                $newSlug=trim((string)($_POST['slug']??''));
                if($oldPost && !empty($oldPost['slug']) && $newSlug!=='' && $oldPost['slug']!==$newSlug){
                    (new RedirectRepository())->upsert('/blog/'.$oldPost['slug'],'/blog/'.$newSlug);
                }
                $this->setFlash('success','Article saved.');
                $this->redirect('/admin/blog/' . $id . '/edit');
            } catch (Throwable $e) {
                $this->setFlash('error','Article could not be saved: ' . $e->getMessage());
                $this->redirect('/admin/blog');
            }
        }

        if ($path === '/admin/guides' && $method === 'GET') {
            View::render('admin/guides', array_merge(['guides'=>$this->repo->guides()], $this->common('Buying Guides')), 'admin/layout');
            return true;
        }
        if ($method === 'GET' && ($path === '/admin/guides/new' || preg_match('#^/admin/guides/(\d+)/edit$#', $path, $m))) {
            $id=isset($m[1]) ? (int)$m[1] : null;
            View::render('admin/guide-form', array_merge([
                'guide'=>$this->repo->guide($id),
                'categories'=>$this->repo->categoryOptions(),
                'productOptions'=>$this->repo->productOptions(),
                'mediaItems'=>$media(),
            ], $this->common($id ? 'Edit Buying Guide' : 'New Buying Guide')), 'admin/layout');
            return true;
        }
        if ($path === '/admin/guides/save' && $method === 'POST') {
            $this->requireCsrf();
            $status=(string)($_POST['status'] ?? 'draft');
            if ($status === 'published' && !Auth::canPublish()) {
                http_response_code(403); echo 'You do not have permission to publish.'; return true;
            }
            $existingId=!empty($_POST['id'])?(int)$_POST['id']:null;
            $oldGuide=$existingId?$this->repo->guide($existingId):null;
            try {
                $id=$this->repo->saveGuide($_POST, (int)Auth::user()['id'],$existingId);
                $newSlug=trim((string)($_POST['slug']??''));
                if($oldGuide && !empty($oldGuide['slug']) && $newSlug!=='' && $oldGuide['slug']!==$newSlug){
                    (new RedirectRepository())->upsert('/guide/'.$oldGuide['slug'],'/guide/'.$newSlug);
                }
                $this->setFlash('success','Buying guide saved.');
                $this->redirect('/admin/guides/' . $id . '/edit');
            } catch (Throwable $e) {
                $this->setFlash('error','Buying guide could not be saved: ' . $e->getMessage());
                $this->redirect('/admin/guides');
            }
        }

        http_response_code(404);
        View::render('404', ['pageTitle'=>'Admin page not found','metaDescription'=>'']);
        return true;
    }

    private function common(string $title): array
    {
        return ['pageTitle'=>$title,'adminUser'=>Auth::user(),'success'=>$this->flash('success'),'error'=>$this->flash('error')];
    }

    private function requireLogin(): void
    {
        if (!Auth::check()) $this->redirect('/admin/login');
    }

    private function requireEditor(): void
    {
        if (!Auth::canManageProducts()) { http_response_code(403); exit('Forbidden'); }
    }

    private function requireCsrf(): void
    {
        if (!Csrf::validate(isset($_POST['_csrf']) ? (string)$_POST['_csrf'] : null)) {
            http_response_code(419); exit('Invalid or expired form token.');
        }
    }

    private function redirect(string $path): never
    {
        header('Location: ' . url($path)); exit;
    }

    private function setFlash(string $key, string $value): void { $_SESSION['_flash'][$key]=$value; }
    private function flash(string $key): ?string
    {
        $value=$_SESSION['_flash'][$key] ?? null; unset($_SESSION['_flash'][$key]); return is_string($value)?$value:null;
    }
}
