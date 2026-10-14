<?php

class HomeController extends Controller
{
    public function detail(): void
    {
        require_auth();
        $id = (int) ($_GET['id'] ?? 0);
        $item = MenuItem::find($id);
        if (!$item || !$item['available']) {
            flash('error', 'That menu item is not available.');
            redirect('home');
        }
        $this->view('home/detail', [
            'title' => $item['name'],
            'item' => $item,
            'cartCount' => Cart::count(),
        ]);
    }

    public function index(): void
    {
        require_auth();
        $this->view('home/index', [
            'title' => 'Home',
            'featured' => MenuItem::featured(),
            'items' => MenuItem::all(null, true),
            'categories' => MenuItem::categories(),
            'cartCount' => Cart::count(),
        ]);
    }
}
