<?php

class IndexController extends Zend_Controller_Action
{

    public function init()
    {
        //Verifica se o usuario esta autenticado, caso não esteja ele é redirecionado para a tela da login
        if ( !Zend_Auth::getInstance()->hasIdentity() ) {
            return $this->_helper->redirector->goToRoute( array('controller' => 'auth'), null, true);
        }

        //Pega as informações do usuario logado no sistema.
        $this->funcLogado = Zend_Auth::getInstance()->getIdentity();
        //Envia pra view
        $this->view->funcLogado = $this->funcLogado;

        //Informações de exibição do usuario no index (deve estar em todos os inits)
        $this->FuncFilial = new Model_DbTable_FuncFilial();
        $dadosIndex = $this->FuncFilial->find($this->funcLogado->idfuncionario);
        $this->view->dadosIndex = $dadosIndex[0];

        //Dados do usuario logado para serem utilizados nas actions
        $idFunc = $this->funcLogado->idfuncionario;
        $idEmpresa = $dadosIndex[0]->empresa_idempresa;
        $idFilial = $this->funcLogado->empresaFilial_idempresaFilial;

        //Informações relativas a permissoes (Se tiver permissão retorna True)
        $adminFilial = Model_Permissoes::responsavelFilial($idFunc,$idFilial);
        $adminEmpresa = Model_Permissoes::responsavelEmpresa($idFunc,$idEmpresa);
        $this->view->AdminFilial = $adminFilial;
        $this->view->AdminEmpresa = $adminEmpresa;


    }

    public function indexAction()
    {
        // action body
    }


}



