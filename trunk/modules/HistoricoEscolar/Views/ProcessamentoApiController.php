<?php

#error_reporting(E_ALL);
#ini_set("display_errors", 1);

/**
 * i-Educar - Sistema de gestão escolar
 *
 * Copyright (C) 2006  Prefeitura Municipal de Itajaí
 *     <ctima@itajai.sc.gov.br>
 *
 * Este programa é software livre; você pode redistribuí-lo e/ou modificá-lo
 * sob os termos da Licença Pública Geral GNU conforme publicada pela Free
 * Software Foundation; tanto a versão 2 da Licença, como (a seu critério)
 * qualquer versão posterior.
 *
 * Este programa é distribuí­do na expectativa de que seja útil, porém, SEM
 * NENHUMA GARANTIA; nem mesmo a garantia implí­cita de COMERCIABILIDADE OU
 * ADEQUAÇÃO A UMA FINALIDADE ESPECÍFICA. Consulte a Licença Pública Geral
 * do GNU para mais detalhes.
 *
 * Você deve ter recebido uma cópia da Licença Pública Geral do GNU junto
 * com este programa; se não, escreva para a Free Software Foundation, Inc., no
 * endereço 59 Temple Street, Suite 330, Boston, MA 02111-1307 USA.
 *
 * @author    Lucas D'Avila <lucasdavila@portabilis.com.br>
 * @category  i-Educar
 * @license   @@license@@
 * @package   Avaliacao
 * @subpackage  Modules
 * @since   Arquivo disponível desde a versão ?
 * @version   $Id$
 */

require_once 'Core/Controller/Page/EditController.php';
require_once 'Avaliacao/Model/NotaComponenteDataMapper.php';
require_once 'Avaliacao/Service/Boletim.php';
require_once 'App/Model/MatriculaSituacao.php';
require_once 'RegraAvaliacao/Model/TipoPresenca.php';
require_once 'RegraAvaliacao/Model/TipoParecerDescritivo.php';
require_once 'include/pmieducar/clsPmieducarMatricula.inc.php';
require_once 'include/portabilis/dal.php';
require_once 'include/pmieducar/clsPmieducarHistoricoEscolar.inc.php';
require_once 'include/pmieducar/clsPmieducarHistoricoDisciplinas.inc.php';

class ProcessamentoApiController extends Core_Controller_Page_EditController
{
  protected $_dataMapper  = 'Avaliacao_Model_NotaComponenteDataMapper';
  protected $_processoAp  = 644;
  protected $_nivelAcessoOption = App_Model_NivelAcesso::SOMENTE_ESCOLA;
  protected $_saveOption  = FALSE;
  protected $_deleteOption  = FALSE;
  protected $_titulo   = '';


  protected function validatesPresenceOf(&$value, $name, $raiseExceptionOnEmpty = false, $msg = '', $addMsgOnEmpty = true){
    if (! isset($value) || (empty($value) && !is_numeric($value))){
      if ($addMsgOnEmpty)
      {
        $msg = empty($msg) ? "É necessário receber uma variavel '$name'" : $msg;
        $this->appendMsg($msg);
      }

      if ($raiseExceptionOnEmpty)
         throw new Exception($msg);

      return false;
    }
    return true;
  }

  protected function validatesValueIsNumeric(&$value, $name, $raiseExceptionOnError = false, $msg = '', $addMsgOnError = true){
    if (! is_numeric($value)){
      if ($addMsgOnError)
      {
        $msg = empty($msg) ? "O valor recebido para variavel '$name' deve ser numerico" : $msg;
        $this->appendMsg($msg);
      }

      if ($raiseExceptionOnError)
         throw new Exception($msg);

      return false;
    }
    return true;
  }

  protected function validatesValueInSetOf(&$value, $setExpectedValues, $name, $raiseExceptionOnError = false, $msg = ''){
    if (! in_array($value, $setExpectedValues)){
      $msg = empty($msg) ? "Valor recebido na variavel '$name' é invalido" : $msg;
      $this->appendMsg($msg);

      if ($raiseExceptionOnError)
         throw new Exception($msg);

      return false;
    }
    return true;
  }


  protected function requiresLogin($raiseExceptionOnEmpty){
    return $this->validatesPresenceOf($this->getSession()->id_pessoa, '', $raiseExceptionOnEmpty, 'Usuário deve estar logado');
  }

  protected function requiresUserIsAdmin($raiseExceptionOnError){

    if($this->getSession()->id_pessoa != 1){
      $msg = "O usuário logado deve ser o admin";
      $this->appendMsg($msg);

      if ($raiseExceptionOnError)
         throw new Exception($msg);

      return false;
    }
    return true;
  }

  protected function validatesPresenceOfInstituicaoId($raiseExceptionOnEmpty){
    return $this->validatesPresenceOf($this->getRequest()->instituicao_id, 'instituicao_id', $raiseExceptionOnEmpty);
  }

  protected function validatesPresenceOfEscolaId($raiseExceptionOnEmpty){
    return $this->validatesPresenceOf($this->getRequest()->escola_id, 'escola_id', $raiseExceptionOnEmpty);
  }

  protected function validatesPresenceOfCursoId($raiseExceptionOnEmpty){
    return $this->validatesPresenceOf($this->getRequest()->curso_id, 'curso_id', $raiseExceptionOnEmpty);
  }

  protected function validatesPresenceOfSerieId($raiseExceptionOnEmpty){
    return $this->validatesPresenceOf($this->getRequest()->serie_id, 'serie_id', $raiseExceptionOnEmpty);
  }

  protected function validatesPresenceOfAno($raiseExceptionOnEmpty){
    return $this->validatesPresenceOf($this->getRequest()->ano, 'ano', $raiseExceptionOnEmpty);
  }

  protected function validatesPresenceOfMatriculaId($raiseExceptionOnEmpty){
    return $this->validatesPresenceOf($this->getRequest()->matricula_id, 'matricula_id', $raiseExceptionOnEmpty);
  }

  protected function validatesValueIsInBd($fieldName, &$value, $schemaName, $tableName, $raiseExceptionOnError = true){

    $sql = "select 1 from $schemaName.$tableName where $fieldName = $value";
    $isValid = $this->db->selectField($sql) == '1';

    if (! $isValid){
      $msg = "O valor informado {$value} para $tableName, não esta presente no banco de dados.";
      $this->appendMsg($msg);

      if ($raiseExceptionOnError)
         throw new Exception($msg);

      return false;
    }
    return true;
  }

  protected function validatesPresenceAndValueInDbOfGradeCursoId($raiseExceptionOnError){
    return $this->validatesPresenceOf($this->getRequest()->grade_curso_id, 'grade_curso_id', $raiseExceptionOnError) &&
            $this->validatesValueIsInBd('id', $this->getRequest()->grade_curso_id, 'pmieducar', 'historico_grade_curso', $raiseExceptionOnError);
  }

  protected function validatesPresenceOfDiasLetivos($raiseExceptionOnEmpty){
    return $this->validatesPresenceOf($this->getRequest()->dias_letivos, 'dias_letivos', $raiseExceptionOnEmpty);
  }

  protected function validatesValueOfAttValueIsNumeric($raiseExceptionOnError){
    return $this->validatesValueIsNumeric($this->getRequest()->att_value, 'att_value', $raiseExceptionOnError);
  }

  protected function validatesPresenceOfAttValue($raiseExceptionOnEmpty){
    return $this->validatesPresenceOf($this->getRequest()->att_value, 'att_value', $raiseExceptionOnEmpty);
  }


  protected function validatesPresenceAndValueInSetOfAtt($raiseExceptionOnError){
    $result = $this->validatesPresenceOf($this->getRequest()->att, 'att', $raiseExceptionOnError);

    if ($result){
      $expectedAtts = array('matriculas', 'processamento', 'historico');
      $result = $this->validatesValueInSetOf($this->getRequest()->att, $expectedAtts, 'att', $raiseExceptionOnError);
    }
    return $result;
  }


  protected function validatesPresenceAndValueInSetOfOper($raiseExceptionOnError){
    $result = $this->validatesPresenceOf($this->getRequest()->oper, 'oper', $raiseExceptionOnError);

    if ($result){
      $expectedOpers = array('post', 'get', 'delete');
      $result = $this->validatesValueInSetOf($this->getRequest()->oper, $expectedOpers, 'oper', $raiseExceptionOnError);
    }
    return $result;
  }


  protected function validatesPresenceAndValueInSetOfExtraCurricular($raiseExceptionOnError){
    $result = $this->validatesPresenceOf($this->getRequest()->extra_curricular, 'extra_curricular', $raiseExceptionOnError);

    if ($result){
      $expectedOpers = array(0, 1);
      $result = $this->validatesValueInSetOf($this->getRequest()->extra_curricular, $expectedOpers, 'extra_curricular', $raiseExceptionOnError);
    }
    return $result;
  }

  protected function validatesPresenceAndValueOfPercentualFrequencia($raiseExceptionOnError){
    $name = 'percentual_frequencia';
    $isValid = $this->validatesPresenceOf($this->getRequest()->percentual_frequencia, $name, $raiseExceptionOnError);

    if ($isValid && $this->getRequest()->percentual_frequencia != 'buscar-boletim')
      $isValid = $this->validatesValueIsNumeric($this->getRequest()->percentual_frequencia, $name, $raiseExceptionOnError);

    return $isValid;
  }

  protected function validatesPresenceOfNotas($raiseExceptionOnError){
    return $this->validatesPresenceOf($this->getRequest()->notas, 'notas', $raiseExceptionOnError);
  }

  protected function validatesPresenceAndValueOfFaltas($raiseExceptionOnError){
    $name = 'faltas';
    $isValid = $this->validatesPresenceOf($this->getRequest()->faltas, $name, $raiseExceptionOnError);

    if ($isValid && $this->getRequest()->faltas != 'buscar-boletim')
      $isValid = $this->validatesValueIsNumeric($this->getRequest()->faltas, $name, $raiseExceptionOnError);

    return $isValid;
  }

  protected function validatesPresenceAndValueInSetOfSituacao($raiseExceptionOnError){
    $name = 'situacao';
    $isValid = $this->validatesPresenceOf($this->getRequest()->situacao, $name, $raiseExceptionOnError);

    if ($isValid){
      $expectedOpers = array('buscar-matricula', 'aprovado', 'reprovado', 'em-andamento', 'transferido');
      $isValid = $this->validatesValueInSetOf($this->getRequest()->situacao, $expectedOpers, $name, $raiseExceptionOnError);
    }

    return $isValid;
  }


  /* esta funcao só pode ser chamada após setar $this->getService() */
  protected function validatesPresenceOfComponenteCurricularId($raiseExceptionOnEmpty, $addMsgOnEmpty = true)
  {
    return $this->validatesPresenceOf($this->getRequest()->componente_curricular_id, 'componente_curricular_id', $raiseExceptionOnEmpty, $msg = '', $addMsgOnEmpty);
  }


  protected function canAcceptRequest()
  {
    try {
      $this->requiresLogin(true);
      $this->requiresUserIsAdmin(true);
      $this->validatesPresenceAndValueInSetOfAtt(true);
      $this->validatesPresenceAndValueInSetOfOper(true);
    }
    catch (Exception $e){
      return false;
    }
    return true;
  }


  protected function canGetMatriculas(){
    return $this->validatesPresenceOfAno(false) &&
           $this->validatesPresenceOfInstituicaoId(false) &&
           $this->validatesPresenceOfEscolaId(false);
  }


  protected function canPostProcessamento(){
    $canPost = $this->validatesPresenceOfInstituicaoId(false) &&
           $this->validatesPresenceOfMatriculaId(false) &&
           $this->validatesPresenceOfDiasLetivos(false) &&
           $this->validatesPresenceAndValueInSetOfSituacao(false) &&
           $this->validatesPresenceAndValueInSetOfExtraCurricular(false) &&
           $this->validatesPresenceAndValueInDbOfGradeCursoId(false) &&
           $this->validatesPresenceAndValueOfPercentualFrequencia(false) &&
           $this->validatesPresenceOfNotas(false) &&
           $this->validatesPresenceAndValueOfFaltas(false);

    if($canPost){
      $sql = "select 1 from pmieducar.matricula where cod_matricula = {$this->getRequest()->matricula_id} and ativo = 1";

      if(! $this->db->selectField($sql)){
        $this->appendMsg("A matricula {$this->getRequest()->matricula_id} não existe ou esta desativa", 'error');
        $canPost = false;
      }
    }

    if($canPost){
      $sql = "select 1 from pmieducar.matricula_turma where ref_cod_matricula = {$this->getRequest()->matricula_id} and ativo = 1 limit 1";

      if(! $this->db->selectField($sql)){
        $this->appendMsg("A matricula {$this->getRequest()->matricula_id} não esta enturmada.", 'error');
        $canPost = false;
      }
    }

    return $canPost && $this->setService();
  }


  protected function canDeleteHistorico(){
    return $this->validatesPresenceOfInstituicaoId(false) &&
    $this->validatesPresenceOfMatriculaId(false);
  }


  protected function deleteHistorico(){

    if ($this->canDeleteHistorico()){

      $matriculaId = $this->getRequest()->matricula_id;
      $alunoId = $this->getAlunoIdByMatriculaId($matriculaId);
      $dadosMatricula = $this->getdadosMatricula($matriculaId);
      $ano = $dadosMatricula['ano'];

      if ($this->existsHistorico($alunoId, $ano, $matriculaId)){
        $dadosHistoricoEscolar = $this->getDadosHistorico($alunoId, $ano, $matriculaId);
        $this->deleteHistoricoDisplinas($alunoId, $dadosHistoricoEscolar['sequencial']);

        $historicoEscolar =  new clsPmieducarHistoricoEscolar(
                                    $ref_cod_aluno = $alunoId,
                                    $sequencial = $dadosHistoricoEscolar['sequencial'],
                                    $ref_usuario_exc = $this->getSession()->id_pessoa,
                                    $ref_usuario_cad = null,
                                    #TODO nm_curso
                                    $nm_serie = null,
                                    $ano = $ano,
                                    $carga_horaria = null,
                                    $dias_letivos = null,
                                    $escola = null,
                                    $escola_cidade = null,
                                    $escola_uf = null,
                                    $observacao = null,
                                    $aprovado = null,
                                    $data_cadastro = null,
                                    $data_exclusao = date('Y-m-d'),
                                    $ativo = 0
                            );
        $historicoEscolar->edita();

        $this->appendMsg('Histórico escolar removido com sucesso', 'success');
      }
      else
        $this->appendMsg("Histórico matricula $matriculaId inexistente ou já removido", 'notice');

      $situacaoHistorico = $this->getSituacaoHistorico($alunoId, $ano, $matriculaId, $reload = true);

      $this->appendResponse('situacao_historico', $situacaoHistorico);
      $this->appendResponse('link_to_historico', '');
    }
  }


  protected function deleteHistoricoDisplinas($alunoId, $historicoSequencial){
    $historicoDisciplinas = new clsPmieducarHistoricoDisciplinas();
    $historicoDisciplinas->excluirTodos($alunoId, $historicoSequencial);
  }


  protected function getdadosEscola($escolaId){

    $sql = "select (select upper(pes.nome) from pmieducar.escola esc, cadastro.pessoa pes where esc.ref_cod_instituicao = {$this->getRequest()->instituicao_id} and esc.cod_escola = $escolaId and pes.idpes = esc.ref_idpes) as nome,

upper((SELECT COALESCE((SELECT COALESCE((SELECT municipio.nome
        FROM public.municipio,
             cadastro.endereco_pessoa,
             cadastro.juridica,
             public.bairro,
             pmieducar.escola
       WHERE endereco_pessoa.idbai = bairro.idbai AND
             bairro.idmun = municipio.idmun AND
             juridica.idpes = endereco_pessoa.idpes AND
             juridica.idpes = escola.ref_idpes AND
             escola.cod_escola = $escolaId),(SELECT endereco_externo.cidade FROM cadastro.endereco_externo, pmieducar.escola WHERE endereco_externo.idpes = escola.ref_idpes AND escola.cod_escola = $escolaId))),(SELECT municipio FROM pmieducar.escola_complemento where ref_cod_escola = $escolaId)))) AS cidade,

(SELECT COALESCE((SELECT COALESCE((SELECT municipio.sigla_uf
        FROM public.municipio,
             cadastro.endereco_pessoa,
             cadastro.juridica,
             public.bairro,
             pmieducar.escola
       WHERE endereco_pessoa.idbai = bairro.idbai AND
             bairro.idmun = municipio.idmun AND
             juridica.idpes = endereco_pessoa.idpes AND
             juridica.idpes = escola.ref_idpes AND
             escola.cod_escola = $escolaId),(SELECT endereco_externo.sigla_uf FROM cadastro.endereco_externo, pmieducar.escola WHERE endereco_externo.idpes = escola.ref_idpes AND escola.cod_escola = $escolaId))),(select inst.ref_sigla_uf from pmieducar.instituicao inst where inst.cod_instituicao = {$this->getRequest()->instituicao_id}))) as uf";

    $dadosEscola = $this->db->select($sql);

    return $dadosEscola[0];
  }


  protected function getNextHistoricoSequencial($alunoId){

    $sql = "select coalesce(max(sequencial), 0) + 1 from pmieducar.historico_escolar where ref_cod_aluno = $alunoId and ativo = 1";

    return $this->db->selectField($sql);
  }


  protected function getNextHistoricoDisciplinasSequencial($historicoSequencial, $alunoId){

    $sql = "select coalesce(max(sequencial), 0) + 1 from pmieducar.historico_disciplinas where ref_sequencial = $historicoSequencial and ref_ref_cod_aluno = $alunoId";

    return $this->db->selectField($sql);
  }


  protected function getSituacaoMatricula(){

    if($this->getRequest()->situacao == 'buscar-matricula'){
      $situacao = $this->getService()->getOption('aprovado');
    }
    else{
      $situacoes = array('aprovado' => App_Model_MatriculaSituacao::APROVADO,
                         'reprovado' => App_Model_MatriculaSituacao::REPROVADO,
                         'em-andamento' => App_Model_MatriculaSituacao::EM_ANDAMENTO,
                         'transferido' => App_Model_MatriculaSituacao::TRANSFERIDO
                   );

      $situacao = $situacoes[$this->getRequest()->situacao];
    }

    return $situacao;

  }

  protected function isFaltaGlobalizada(){
    return ($this->getService()->getRegra()->get('tipoPresenca') == RegraAvaliacao_Model_TipoPresenca::GERAL ? 1 : 0);
  }

 
  protected function getPercentualFrequencia(){
    if($this->getRequest()->percentual_frequencia == 'buscar-boletim')
      return round($this->getService()->getSituacaoFaltas()->porcentagemPresenca, 2);
    else
      return $this->getRequest()->percentual_frequencia;
  }


  protected function postProcessamento()  {

    if ($this->canPostProcessamento()){
      $matriculaId = $this->getRequest()->matricula_id;
      $successMsg = '';

      try {
        $alunoId = $this->getAlunoIdByMatriculaId($matriculaId);
        $dadosMatricula = $this->getdadosMatricula($matriculaId);
        $dadosEscola = $this->getdadosEscola($dadosMatricula['escola_id']);
        $ano = $dadosMatricula['ano'];
        $isNewHistorico = ! $this->existsHistorico($alunoId, $ano, $matriculaId);

          if ($isNewHistorico){
          $sequencial = $this->getNextHistoricoSequencial($alunoId);

          $historicoEscolar =  new clsPmieducarHistoricoEscolar(
                                  $ref_cod_aluno = $alunoId,
                                  $sequencial = $sequencial,
                                  $ref_usuario_exc = null,
                                  $ref_usuario_cad = $this->getSession()->id_pessoa,
                                  $nm_serie = $dadosMatricula['nome_serie'],
                                  $ano = $ano,
                                  $carga_horaria = $this->getService()->getOption('serieCargaHoraria'),
                                  $dias_letivos = $this->getRequest()->dias_letivos,
                                  $escola = $dadosEscola['nome'],
                                  $escola_cidade = $dadosEscola['cidade'],
                                  $escola_uf = $dadosEscola['uf'],
                                  $observacao = $this->getRequest()->observacao,
                                  $aprovado = $this->getSituacaoMatricula(),
                                  $data_cadastro = date('Y-m-d'),
                                  $data_exclusao = null,
                                  $ativo = 1,
                                  $faltas_globalizadas = $this->isFaltaGlobalizada(),
                                  $ref_cod_instituicao = $dadosMatricula['instituicao_id'],
                                  $origem = '', #TODO
                                  $extra_curricular = $this->getRequest()->extra_curricular,
                                  $ref_cod_matricula = $matriculaId,
                                  $frequencia = $this->getPercentualFrequencia(),
                                  $registro = $this->getRequest()->registro,
                                  $livro = $this->getRequest()->livro,
                                  $folha = $this->getRequest()->folha,
                                  $nm_curso = $dadosMatricula['nome_curso'],
                                  $historico_grade_curso_id = $this->getRequest()->grade_curso_id
                                );

          $historicoEscolar->cadastra();
          $this->recreateHistoricoDisciplinas($sequencial, $alunoId);

          $successMsg = 'Histórico processado com sucesso';
        }
        else{

          $dadosHistoricoEscolar = $this->getDadosHistorico($alunoId, $ano, $matriculaId);

          $historicoEscolar =  new clsPmieducarHistoricoEscolar(
                                  $ref_cod_aluno = $alunoId,
                                  $sequencial = $dadosHistoricoEscolar['sequencial'],
                                  $ref_usuario_exc = $this->getSession()->id_pessoa,
                                  $ref_usuario_cad = $dadosHistoricoEscolar['ref_usuario_cad'],
                                  $nm_serie = $dadosMatricula['nome_serie'],
                                  $ano = $ano,
                                  $carga_horaria = $this->getService()->getOption('serieCargaHoraria'),
                                  $dias_letivos = $this->getRequest()->dias_letivos,
                                  $escola = $dadosEscola['nome'],
                                  $escola_cidade = $dadosEscola['cidade'],
                                  $escola_uf = $dadosEscola['uf'],
                                  $observacao = $this->getRequest()->observacao,
                                  $aprovado = $this->getSituacaoMatricula(),
                                  $data_cadastro = null,
                                  $data_exclusao = null,
                                  $ativo = 1,
                                  $faltas_globalizadas = $this->isFaltaGlobalizada(),
                                  $ref_cod_instituicao = $dadosMatricula['instituicao_id'],
                                  $origem = '', #TODO
                                  $extra_curricular = $this->getRequest()->extra_curricular,
                                  $ref_cod_matricula = $matriculaId,
                                  $frequencia = $this->getPercentualFrequencia(),
                                  $registro = $this->getRequest()->registro,
                                  $livro = $this->getRequest()->livro,
                                  $folha = $this->getRequest()->folha,
                                  $nm_curso = $dadosMatricula['nome_curso'],
                                  $historico_grade_curso_id = $this->getRequest()->grade_curso_id
                                );

          $historicoEscolar->edita();
          $this->recreateHistoricoDisciplinas($dadosHistoricoEscolar['sequencial'], $alunoId);
          $successMsg = 'Histórico reprocessado com sucesso';
        }

      }
      catch (Exception $e){
        $this->appendMsg('Erro ao processar histórico, detalhes:' . $e->getMessage(), 'error', true);
        return false;
      }

      $situacaoHistorico = $this->getSituacaoHistorico($alunoId, $ano, $matriculaId, $reload = true);
      $linkToHistorico = $this->getLinkToHistorico($alunoId, $ano, $matriculaId);

      $this->appendResponse('situacao_historico', $situacaoHistorico);
      $this->appendResponse('link_to_historico', $linkToHistorico);

      if ($successMsg)
        $this->appendMsg($successMsg, 'success');
      else
        $this->appendMsg('Histórico não reprocessado', 'notice');
    }
  }


  protected function recreateHistoricoDisciplinas($historicoSequencial, $alunoId){

    $this->deleteHistoricoDisplinas($alunoId, $historicoSequencial);

    $cnsPresenca = RegraAvaliacao_Model_TipoPresenca;
    $tpPresenca = $this->getService()->getRegra()->get('tipoPresenca');
    $cnsNota = RegraAvaliacao_Model_Nota_TipoValor;
    $tpNota = $this->getService()->getRegra()->get('tipoNota');
    $situacaoFaltasCc = $this->getService()->getSituacaoFaltas()->componentesCurriculares;
    $mediasCc = $this->getService()->getMediasComponentes();

    foreach ($this->getService()->getComponentes() as $componenteCurricular)
    {
      $ccId = $componenteCurricular->get('id');
      $sequencial = $this->getNextHistoricoDisciplinasSequencial($historicoSequencial, $alunoId);
      $situacaoFaltaCc = $situacaoFaltasCc[$ccId];

      if($tpPresenca == $cnsPresenca::POR_COMPONENTE){
        $falta = $situacaoFaltaCc->total;
      }
      elseif($tpPresenca == $cnsPresenca::GERAL){
        $falta = $this->getService()->getSituacaoFaltas()->totalFaltas;
      }

      if (($tpNota == $cnsNota::NUMERICA || $tpNota == $cnsNota::CONCEITUAL)){
        if(is_array($mediasCc[$ccId]) && count($mediasCc[$ccId]) > 0)
          $nota = (string)$mediasCc[$ccId][0]->mediaArredondada;
        else
          $nota = '';
      }
      else
        $nota = '';

      $historicoDisciplina = new clsPmieducarHistoricoDisciplinas(
                                $sequencial, 
                                $alunoId,
                                $historicoSequencial,
                                $componenteCurricular->nome,
                                $nota,
                                $falta
                            );

      $historicoDisciplina->cadastra();
    }
  }


  protected function getDadosMatricula($matriculaId){

    $ano = $this->getAnoMatricula($matriculaId);

    $sql = "select ref_ref_cod_serie as serie_id, ref_cod_curso as curso_id from pmieducar.matricula where cod_matricula = $matriculaId";
    $idsSerieCurso = $this->db->select($sql);
    $idsSerieCurso = $idsSerieCurso[0];  

    $matriculas = array();

    $matriculaTurma = new clsPmieducarMatriculaTurma();
    $matriculaTurma = $matriculaTurma->lista(
      $matriculaId,
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      1,
      $idsSerieCurso['serie_id'],
      $idsSerieCurso['curso_id']
    );
    $matriculaTurma = $matriculaTurma[0];

    $dadosMatricula = array();
    if (is_array($matriculaTurma) && count($matriculaTurma) > 0){
      $dadosMatricula['ano'] = $ano;
      $dadosMatricula['instituicao_id'] = $matriculaTurma['ref_cod_instituicao'];
      $dadosMatricula['escola_id'] = $matriculaTurma['ref_ref_cod_escola'];
      $dadosMatricula['serie_id'] = $matriculaTurma['ref_ref_cod_serie'];
      $dadosMatricula['matricula_id'] = $matriculaTurma['ref_cod_matricula'];
      $dadosMatricula['aluno_id'] = $matriculaTurma['ref_cod_aluno'];
      $dadosMatricula['nome'] = ucwords(strtolower(utf8_decode($matriculaTurma['nome_aluno'])));
      $dadosMatricula['nome_curso'] = ucwords(strtolower($matriculaTurma['nm_curso']));
      $dadosMatricula['nome_serie'] = strtolower(utf8_decode($this->getNomeSerie($matriculaTurma['ref_ref_cod_serie'])));
      $dadosMatricula['nome_turma'] = ucwords(strtolower(utf8_decode($matriculaTurma['nm_turma'])));
      $dadosMatricula['situacao_historico'] = $this->getSituacaoHistorico($matriculaTurma['ref_cod_aluno'], $ano, $matriculaId);
      $dadosMatricula['link_to_historico'] = $this->getLinkToHistorico($matriculaTurma['ref_cod_aluno'], $ano, $matriculaId);
    }
    else{
      throw new Exception("Não foi possivel recuperar os dados da matricula: $matriculaId.");
    }

    return $dadosMatricula;
  }


  protected function getAlunoIdByMatriculaId($matriculaId){
    $sql = "select ref_cod_aluno from pmieducar.matricula where cod_matricula = $matriculaId";

    return $this->db->selectField($sql);
  }


  protected function getAnoMatricula($matriculaId){
    $sql = "select ano from pmieducar.matricula where cod_matricula = $matriculaId";

    return $this->db->selectField($sql);
  }


  protected function getNomeSerie($serieId){
    $sql = "select nm_serie from pmieducar.serie where cod_serie = $serieId";
    $nome = $this->db->select($sql);
    return ucwords(strtolower(utf8_encode($nome[0]['nm_serie'])));
  }


  protected function getDadosHistorico($alunoId, $ano, $matriculaId){
    $sql = "select sequencial from pmieducar.historico_escolar where ref_cod_aluno = $alunoId and ano = $ano and ref_cod_instituicao = {$this->getRequest()->instituicao_id} and ref_cod_matricula = $matriculaId and ativo = 1 limit 1";
    $record = $this->db->select($sql);
    return $record[0];
  }


  protected function existsHistorico($alunoId, $ano, $matriculaId, $ativo = 1, $reload = false){

    if(! isset($this->existsHistorico) || $reload){
      $sql = "select 1 from pmieducar.historico_escolar where ref_cod_aluno = $alunoId and ano = $ano and ref_cod_instituicao = {$this->getRequest()->instituicao_id} and ref_cod_matricula = $matriculaId and ativo = $ativo";
      $this->existsHistorico = ($this->db->selectField($sql) == '1');
    }

    return $this->existsHistorico;
  }


  protected function getSituacaoHistorico($alunoId, $ano, $matriculaId, $reload = false){
    if ($this->existsHistorico($alunoId, $ano, $matriculaId, 1, $reload))
        $situacao = 'Histórico processado';
    else 
        $situacao = 'Não processado';

    return ucwords(strtolower(utf8_encode($situacao)));
  }


  protected function getLinkToHistorico($alunoId, $ano, $matriculaId){
    $sql = "select sequencial from pmieducar.historico_escolar where ref_cod_aluno = $alunoId and ano = $ano and ref_cod_instituicao = {$this->getRequest()->instituicao_id} and ref_cod_matricula = $matriculaId";

    $sequencial = $this->db->selectField($sql);
    
    if (is_numeric($sequencial))
        $link = "/intranet/educar_historico_escolar_det.php?ref_cod_aluno=$alunoId&sequencial=$sequencial";
    else 
        $link = '';

    return $link;
  }


  protected function getMatriculas(){
    $matriculas = array();

    if ($this->canGetMatriculas()){

      
      $alunos = new clsPmieducarMatriculaTurma();
      $alunos->setOrderby('ref_cod_curso, ref_ref_cod_serie, ref_cod_turma, nome');

      $alunos = $alunos->lista(
        $this->getRequest()->matricula_id,
        $this->getRequest()->turma_id,
        NULL,
        NULL,
        NULL,
        NULL,
        NULL,
        NULL,
        1,
        $this->getRequest()->serie_id,
        $this->getRequest()->curso_id,
        $this->getRequest()->escola_id,
        $this->getRequest()->instituicao_id,
        $this->getRequest()->aluno_id,
        NULL,
        NULL,
        NULL,
        NULL,
        $this->getRequest()->ano,
        NULL,
        TRUE,
        NULL,
        NULL,
        TRUE,
        NULL,
        NULL,
        NULL,
        NULL,
        NULL,
        NULL
      );
      

      if (! is_array($alunos))
        $alunos = array();

      foreach($alunos as $aluno)
      {
        $matricula = array();
        $matriculaId = $aluno['ref_cod_matricula'];
        $matricula['matricula_id'] = $matriculaId;
        $matricula['aluno_id'] = $aluno['ref_cod_aluno'];
        $matricula['nome'] = ucwords(strtolower(utf8_encode($aluno['nome_aluno'])));
        $matricula['nome_curso'] = ucwords(strtolower(utf8_encode($aluno['nm_curso'])));
        $matricula['nome_serie'] = ucwords(strtolower(utf8_encode($this->getNomeSerie($aluno['ref_ref_cod_serie']))));
        $matricula['nome_turma'] = ucwords(strtolower(utf8_encode($aluno['nm_turma'])));
        $matricula['situacao_historico'] = $this->getSituacaoHistorico($aluno['ref_cod_aluno'], $this->getRequest()->ano, $matriculaId, $reload = true);
        $matricula['link_to_historico'] = $this->getLinkToHistorico($aluno['ref_cod_aluno'], $this->getRequest()->ano, $matriculaId);
        $matriculas[] = $matricula;
      }
    }

    return $matriculas;
  }

  protected function saveService()
  {
    try {
      $this->getService()->save();   
    }
    catch (CoreExt_Service_Exception $e){
      //excecoes ignoradas :( servico lanca excecoes de alertas, que não são exatamente erros.
      error_log('CoreExt_Service_Exception ignorada: ' . $e->getMessage());
    }
  }

  protected function getService($raiseExceptionOnErrors = false, $appendMsgOnErrors = true){
    if (isset($this->service) && ! is_null($this->service))
      return $this->service;

    $msg = 'Erro ao recuperar serviço boletim: serviço não definido.';
    if($appendMsgOnErrors)
      $this->appendMsg($msg);

    if ($raiseExceptionOnErrors)
      throw new Exception($msg);

    return null;
  }

  protected function canSetService($validatesPresenceOfMatriculaId = true)
  {
    try {
      $this->requiresLogin(true);
      if ($validatesPresenceOfMatriculaId)
        $this->validatesPresenceOfMatriculaId(true);
    }
    catch (Exception $e){
      return false;
    }
    return true;
  }

  protected function setService($matriculaId = null){
    if ($this->canSetService($validatesPresenceOfMatriculaId = is_null($matriculaId))){
      try {

        if (! $matriculaId)
          $matriculaId = $this->getRequest()->matricula_id;

        $this->service = new Avaliacao_Service_Boletim(array(
            'matricula' => $matriculaId,
            'usuario'   => $this->getSession()->id_pessoa
        ));

      return true;
      }
      catch (Exception $e){
        $this->appendMsg('Exception ao instanciar serviço boletim: ' . $e->getMessage(), 'error', $encodeToUtf8 = true);
      }
    }
    return false;
  }


  protected function notImplementedError()
  {
    $this->appendMsg("Operação '{$this->getRequest()->oper}' inválida para o att '{$this->getRequest()->att}'");    
  }


  public function Gerar(){
    $this->msgs = array();
    $this->response = array();
    $this->db = new Db();

    if ($this->canAcceptRequest()){
      try {

        if(isset($this->getRequest()->matricula_id))
          $this->appendResponse('matricula_id', $this->getRequest()->matricula_id);

        if ($this->getRequest()->oper == 'get')
        {
          if ($this->getRequest()->att == 'matriculas')
          {
            $matriculas = $this->getMatriculas();
            $this->appendResponse('matriculas', $matriculas);
          }
          else
            $this->notImplementedError();

        }
        elseif ($this->getRequest()->oper == 'post')
        {
          if ($this->getRequest()->att == 'processamento')
          {
            $this->postProcessamento();
          }
          else
            $this->notImplementedError();  
        }
        elseif ($this->getRequest()->oper == 'delete')
        {
          if ($this->getRequest()->att == 'historico')
          {
            $this->deleteHistorico();
          }
          else
            $this->notImplementedError();
        }
      }
      catch (Exception $e){
        $this->appendMsg('Exception: ' . $e->getMessage(), $type = 'error', $encodeToUtf8 = true);
      }
    }
    echo $this->prepareResponse();
  }

  protected function appendResponse($name, $value){
    $this->response[$name] = $value;
  }

  protected function prepareResponse(){
    $msgs = array();
    $this->appendResponse('att', isset($this->getRequest()->att) ? $this->getRequest()->att : '');

    foreach($this->msgs as $m)
      $msgs[] = array('msg' => $m['msg'], 'type' => $m['type']);
    $this->appendResponse('msgs', $msgs);

    echo json_encode($this->response);
  }

  protected function appendMsg($msg, $type="error", $encodeToUtf8 = false){
    if ($encodeToUtf8)
      $msg = utf8_encode($msg);

    error_log("$type msg: '$msg'");
    $this->msgs[] = array('msg' => $msg, 'type' => $type);
  }

  public function generate(CoreExt_Controller_Page_Interface $instance){
    header('Content-type: application/json');
    $instance->Gerar();
  }
}
