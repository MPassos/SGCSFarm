<?php

class ProjetoController extends Zend_Controller_Action
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


        $this->project = new Model_DbTable_Proj();
        $this->funcionario = new Model_DbTable_Func();
        $this->estado = new Model_DbTable_Estado();
        $this->projbugzilla = new Model_DbTable_ProjBugzilla();
        $this->projgit = new Model_DbTable_ProjGit();
        $this->funcaoproj = new Model_DbTable_FuncaoProjeto();
        $this->colab = new Model_DbTable_Colaboradores();
        $this->empresafilial = new Model_DbTable_Filial();
        $this->funcaoColabProj = new Model_DbTable_FuncaoColabProj();
        $this->tipoEstadoTarefaColabProj = new Model_DbTable_TipoEstadoTarefaColabProj();
        $this->colabProj = new Model_DbTable_ColaboradoresProjetos();
        $this->ProjGerFiliColab = new Model_DbTable_ProjGerFiliColab();
    }

    public function indexAction()
    {
        $idFuncLogado = $this->funcLogado->idfuncionario;
        $this->view->idFuncLogado = $idFuncLogado;
        
        $selectProjs = $this->ProjGerFiliColab->select()
                ->from(array('p' => 'projetos_gerente_filial_colaboradores'),
                        array('idprojeto', 'nomeProj', 'dataFim', 'dataInc', 'estadoProj', 'idGerente',
                    'nomeGerente', 'descricaoProj'))
                ->distinct()
                ->where('idGerente = ?', $idFuncLogado)
                ->orWhere('idFuncionarioColaborador = ?', $idFuncLogado);
        
        $rows = $this->ProjGerFiliColab->fetchAll($selectProjs);
        
        $paginator = Zend_Paginator::factory($rows);
        //Passa o numero de registros por pagina
        $paginator->setItemCountPerPage(4);

        $this->view->paginator = $paginator;
        $paginator->setCurrentPageNumber($this->_getParam('page'));
    }

    public function createAction()
    {
        $this->view->funcionario = $this->funcionario->fetchAll();
                
                
        if($this->_request->isPost())    
        {
            $dataInc = $this->inverte_data($this->_request->getPost('dtinicio'), "/");
            $dataInc = $dataInc." ".date("H:i:s");
            
            $data = array
            (
                'nome' => $this->_request->getPost('nome'),
                'descricao' => $this->_request->getPost('descricao'),
                'dataInc' => $dataInc,
                'dataFim' => "",
                'idGerente' => $this->_request->getPost('idGerente'),
                'estado_idestado' => "2"  
            );

            $idprojetoinserido = $this->project->insert($data);

            $data1 = array
            (
                'projeto_idprojeto' => $idprojetoinserido,
                'nomeProjeto' => $this->_request->getPost('nome')  
            );

            $this->projbugzilla->insert($data1);

            $data2 = array
            (
                'projeto_idprojeto' => $idprojetoinserido,
                'repositorio' => $this->_request->getPost('nome'),
                'chave' => "asdasdsda"
            );

            $this->projgit->insert($data2);

            $this->_redirect('projeto/index');
        }
    }

    public function editAction()
    {
        $id_proj = $this->_getParam('id');
                
    $result = $this->project->find($id_proj);
    $this->view->projeto = $result->current();
    $this->view->funcionario = $this->funcionario->fetchAll();
    $this->view->estado = $this->estado->fetchAll();

    if($this->_request->isPost())
    {
        $data = array
        (
            'nome' => $this->_request->getPost('nome'),
            'descricao' => $this->_request->getPost('descricao'),
            'dataInc' => $this->_request->getPost('dtinicio'),
            'dataFim' => $this->_request->getPost('dtfim'),
            'idGerente' => $this->_request->getPost('idGerente'),
            'estado_idestado' => $this->_request->getPost('estado')  
        );

        $where = $this->project->getAdapter()->quoteInto('idprojeto = ?', (int) $this->_request->getPost('id'));

        $this->project->update($data,$where);

        $this->_redirect('projeto/index');
        }
    }

    public function deleteAction()
    {
        //Precisa só colocar as flags informando o ocorrido para o usuário
                $id_proj = $this->_getParam('id');
                
                $select = $this->project->select();
                $select -> from($this->project, 'COUNT(*) AS num')
                        -> where('idprojeto = ?', $id_proj)
                        ->where('estado_idestado = 7');
                
                if($this->project->fetchRow($select)->num != 0)
                {
                    $where = $this->projbugzilla->getAdapter()->quoteInto('projeto_idprojeto = ?',$id_proj);
                    $this->projbugzilla->delete($where);
                    
                    $where = $this->projgit->getAdapter()->quoteInto('projeto_idprojeto = ?',$id_proj);
                    $this->projgit->delete($where);
                    
                    $where = $this->project->getAdapter()->quoteInto('idprojeto = ?',$id_proj);
                    $this->project->delete($where);
                    
                    $this->_redirect('projeto/index');
                }else{
                    $this->_redirect('projeto/index');
                }
    }

    public function colabAction()
    {
        $id_proj = $this->_getParam('id');
                
        $result = $this->project->find($id_proj);

        $idgerente= $this->project->select();
        $idgerente -> from($this->project,array('idGerente'))
                    -> where('idprojeto = ?',$id_proj);

        $func = $this->funcionario->select();
        $func -> where('idfuncionario != ?',$idgerente);

        $funcao = $this->funcaoproj->select();
        $funcao -> where('idfuncaoProjeto != 40');

        $this->view->projeto = $result->current();
        $this->view->funcionario = $this->funcionario->fetchAll($func);
        $this->view->funcao = $this->funcaoproj->fetchAll($funcao);

        if($this->_request->isPost())
        {
            $data = array
            (
                'projeto_idprojeto' => $this->_request->getPost('id'),
                'funcionario_idfuncionario' => $this->_request->getPost('funcionario'),
                'funcaoProjeto_idfuncaoProjeto' => $this->_request->getPost('funcao')
            );

            $this->colab->insert($data);

            $this->_redirect('projeto/index');
        }
    }

    public function detalhesAction(){
        
        $idFuncLogado = $this->funcLogado->idfuncionario;
        $this->view->idFuncLogado = $idFuncLogado;
        
        $idProj = $this->_getParam('idProj');
        $where = $this->ProjGerFiliColab->getAdapter()->quoteInto('idprojeto = ?', $idProj);
        $selecProj = $this->ProjGerFiliColab->select()
                ->where($where);
        $ProjEncontrado = $this->ProjGerFiliColab->fetchAll($selecProj);
        $this->view->ProjEncontrado = $ProjEncontrado;
        
        $nomeProj = $ProjEncontrado->current()->nomeProj;
        $selectEstadoTarefa = $this->tipoEstadoTarefaColabProj->select()
                    ->where('nomeProj = ?', $nomeProj);
        $rows = $this->tipoEstadoTarefaColabProj->fetchAll($selectEstadoTarefa);
        
        $paginator = Zend_Paginator::factory($rows);
        //Passa o numero de registros por pagina
        $paginator->setItemCountPerPage(4);

        $this->view->paginator = $paginator;
        $paginator->setCurrentPageNumber($this->_getParam('page'));
        /*
         * Codigo que estava funcionando
         */
        
//        $idProj = $this->_getParam('idProj');
//        
//        $idFilialFunc = $this->funcLogado->empresaFilial_idempresaFilial;
//        $idFunc = $this->funcLogado->idfuncionario;
//        $selectFilial = $this->empresafilial->select()
//                ->where('idempresaFilial = ?', $idFilialFunc);
//        $filialEncontrada = $this->empresafilial->fetchRow($selectFilial);
//
//        $selectProj = $this->project->find($idProj);
//        $rows = $selectProj->current();
//        $this->view->projeto = $rows;
//             
//        $selectFunc = $this->funcionario->select();
//        $selectFunc = $this->estado->fetchAll($selectFunc);
//        $this->view->funcionario = $selectFunc;
//        
//        $selectEstado = $this->estado->select();
//        $estadoProj = $this->estado->fetchAll($selectEstado);
//        $this->view->estado = $estadoProj;
//
//        if($idFunc == $filialEncontrada->responsavel){
//            $eResponsavel = 1;
//
//            $this->view->eResponsavel = $eResponsavel;
//            
//            $selectFunc = $this->funcionario->select();
//            $selectFunc = $this->funcionario->fetchAll($selectFunc);
//            $this->view->funcionario = $selectFunc;
//            
//            
//            
//            $where = $this->project->getAdapter()->quoteInto('idprojeto = ?', $idProj);
//            $select = $this->project->select()
//                    ->where($where);
//            $ProjEncontrado = $this->project->fetchRow($select);
//            $nomeProj = $ProjEncontrado->nome;
//            
//            $selectFuncaoColabProj = $this->funcaoColabProj->select()
//                        ->where('nomeProj = ?', $nomeProj);
//            $selectFuncaoColabProj = $this->funcaoColabProj->fetchAll($selectFuncaoColabProj);
//            $this->view->funcaoColabProj = $selectFuncaoColabProj;
//            
//            
//            $selectEstadoTarefa = $this->tipoEstadoTarefaColabProj->select()
//                        ->where('nomeProj = ?', $nomeProj);
//            $selectEstadoTarefa = $this->tipoEstadoTarefaColabProj->fetchAll($selectEstadoTarefa);
//            $this->view->tipoEstadoTarefaColabProj = $selectEstadoTarefa;
//            
//        }else{
//            
//            $where = $this->project->getAdapter()->quoteInto('idprojeto = ?', $idProj);
//            $select = $this->project->select()
//                    ->where($where);
//            $ProjEncontrado = $this->project->fetchRow($select);
//            $nomeProj = $ProjEncontrado->nome;
//            
//            $selectFuncaoColabProj = $this->funcaoColabProj->select()
//                        ->where('nomeProj = ?', $nomeProj);
//            $selectFuncaoColabProj = $this->funcaoColabProj->fetchAll($selectFuncaoColabProj);
//            $this->view->funcaoColabProj = $selectFuncaoColabProj;
//            
//            
//            $selectEstadoTarefa = $this->tipoEstadoTarefaColabProj->select()
//                        ->where('nomeProj = ?', $nomeProj);
//            $selectEstadoTarefa = $this->tipoEstadoTarefaColabProj->fetchAll($selectEstadoTarefa);
//            $this->view->tipoEstadoTarefaColabProj = $selectEstadoTarefa;
//        }
    }
    
    /** 
     * Função para a inversão de datas.
     * Exemplo dd/mm/yyyy é convertido em yyyy/mm/dd e vice-versa
     * recebe a data e o tipo de separador utilizado
     * retorna a data invertida.
     * 
     * @access private 
     * @param String $data
     * @param String $separador
     * @return string $nova_data
     */ 
    private function inverte_data($data, $separador)
    {
        $nova_data = implode("".$separador."",array_reverse(explode("".$separador."",$data)));
        return $nova_data;
    }

}



