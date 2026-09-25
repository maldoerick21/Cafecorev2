<?php

class HomeController extends Controller
{
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
