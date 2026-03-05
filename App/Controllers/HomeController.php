<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\UserModel;

class HomeController extends Controller {

    public function index(): void {
        $this->requireLoginRedirect();

        $userModel = new UserModel();
        $userData  = $userModel->findById(
            (int) $_SESSION['logado']->id,
            ['id', 'login', 'nome', 'perfil', 'status']
        );

        $this->view('head_html', []);

        $this->view('header_app', [
            'nome'   => $userData->nome,
            'perfil' => $userData->perfil,
        ]);

        $this->view('menu_html', [
            'perfil' => $userData->perfil,
        ]);

        $this->view('container_html', []);

        $this->view('footer_html', [
            'nome_usuario'   => $userData->nome,
            'pagina_inicial' => 'usuarios',
        ]);
    }
}