<?php
/**
 * Cliente_Portabilidade
 *
 * Consulta a operadora de celular
 *
 * Licença
 *
 * Este código fonte está sob a licença Creative Commons, você pode ler mais
 * sobre os termos na URL: http://creativecommons.org/licenses/by-sa/2.5/br/
 *
 * @copyright  Thiago Paes <thiago@thiagopaes.com.br> (c) 2010
 * @license    http://creativecommons.org/licenses/by-sa/2.5/br/
 */
class Portabilidade
{
    /**
     *
     * @access private
     * @var string
     */
    private $_cookie = '/tmp/cookies_portabilidade.txt';

    /**
     *
     * @access private
     * @var string
     */
    private $_captcha = '/tmp/captcha_portabilidade.jpg';

    /**
     *
     * @access private
     * @var string
     */
    private $_url = 'http://consultanumero.abr.net.br:8080/consultanumero';

    /**
     *
     * @access private
     * @var object
     */
    private $_curl;

    /**
     *
     * @access private
     * @var string
     */
    private $_jcid;

    /**
     * Telefone do usuário
     *
     * @access private
     * @var string
     */
    private $_telefone;

    /**
     * Construtor
     *
     * @access public
     * @return void
     */
    public function __construct ()
    {
        // verificando se existe a biblioteca GD
        if (! function_exists('imagecreatefrompng')) {
            throw new Exception('Biblioteca GD não encontrada!');
        }
        
        // verificando o módulo do curl
        if (! function_exists('curl_init')) {
            throw new Exception('Biblioteca Curl não encontrada!');
        }
        
        // iniciando cURL
        $this->_curl = curl_init();
        
        $agent = 'Mozilla/5.0 (Windows; U; Windows NT 5.1; pt-BR; rv:1.9.1.5) ';
        $agent .= 'Gecko/20091102 Firefox/3.5.5';
        
        curl_setopt($this->_curl, CURLOPT_USERAGENT, $agent);
        curl_setopt($this->_curl, CURLOPT_TIMEOUT, 100);
        curl_setopt($this->_curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->_curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->_curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->_curl, CURLOPT_AUTOREFERER, true);
        curl_setopt($this->_curl, CURLOPT_COOKIEJAR, $this->_cookie);
        curl_setopt($this->_curl, CURLOPT_COOKIEFILE, $this->_cookie);
    }

    /**
     * Faço um post pelo Curl
     *
     * @access private
     * @param string $strEndereco            
     * @param array $arrPost            
     * @param string $referer            
     * @return string
     */
    private function post ($strEndereco, $arrPost, $referer = null)
    {
        $strPost = http_build_query($arrPost);
        
        curl_setopt($this->_curl, CURLOPT_REFERER, $this->_url);
        curl_setopt($this->_curl, CURLOPT_URL, $strEndereco);
        curl_setopt($this->_curl, CURLOPT_POST, true);
        curl_setopt($this->_curl, CURLOPT_POSTFIELDS, $strPost);
        
        return curl_exec($this->_curl);
    }

    /**
     * Faço um get pelo Curl
     *
     * @access private
     * @param string $strEndereco            
     * @param array $arrPost            
     * @param string $referer            
     * @return string
     */
    private function get ($strEndereco, $referer = null)
    {
        curl_setopt($this->_curl, CURLOPT_REFERER, $this->_url);
        curl_setopt($this->_curl, CURLOPT_URL, $strEndereco);
        curl_setopt($this->_curl, CURLOPT_POST, false);
        
        return curl_exec($this->_curl);
    }

    /**
     * Busca o captcha e tenta quebrar
     *
     * @access private
     * @return string
     */
    private function baixaCaptcha ()
    {
        // envio a primeira chamada
        $url = $this->_url . '/consulta/consultaSituacaoAtual.action';
        $retorno = $this->get($url);
        
        preg_match_all('/jcid=([[:alnum:]])+/i', $retorno, $jcids);
        $this->_jcid = str_replace('jcid=', '', $jcids[0][0]);
        
        // Pegando o captcha, que tem sempre o mesmo nome
        $ret = $this->get($this->_url . '/jcaptcha.jpg?jcid=' . $this->_jcid);
        
        file_put_contents($this->_captcha, $ret);
        
        if (filesize($this->_captcha) === 0) {
            throw new Exception('Erro baixando captcha.');
        }
        
        return $this;
    }

    public function setTelefone ($telefone)
    {
        $this->_telefone = $this->limpaTelefone($telefone);
        
        return $this;
    }

    public function getTelefone ()
    {
        return $this->_telefone;
    }

    /**
     * Limpa o telefone dado como entrada
     *
     * @access private
     * @param integer $telefone            
     * @return integer
     */
    private function limpaTelefone ($telefone = null)
    {
        // verifico se existe um telefone de destino
        if ($telefone === null || strlen($telefone) !== 10) {
            throw new Exception('Telefone inválido.');
        }
        
        // limpo o telefone
        if ($telefone !== null) {
            $telefone = preg_replace('/[^[:digit:]]/', '', $telefone);
            
            return $telefone;
        }
    }

    /**
     * Segunda etapa, envio da mensagem em si
     *
     * @access public
     * @return string
     */
    public function consulta ()
    {
        // pego o nome dos campos
        $campos = array(
            'nmTelefone' => $this->getTelefone(),
            'j_captcha_response' => $this->baixaCaptcha(),
            'jcid' => $this->_jcid,
            'method:consultar' => 'Consultar'
        );
        
        // enviando o captcha
        $url = $this->_url . '/consulta/consultaSituacaoAtual.action';
        $retorno = $this->post($url, $campos);
        
        return $this->trataRetorno($retorno);
    }

    /**
     * Treta o retorno da consulta em busca da string válida
     *
     * @access private
     * @param string $retorno            
     * @return string
     */
    private function trataRetorno ($retorno)
    {
        // removendo quebras de linha
        $retorno = preg_replace('/(\r|\n)/', '', $retorno);
        
        // Buscando pela operadora
        $er = '/(<tr class="gridselecionado">(.+)<\/tr>)/i';
        preg_match_all($er, $retorno, $resposta);
        
        if (isset($resposta[0][0]) && strlen($resposta[0][0]) > 2) {
            $retorno = explode('<td>', $resposta[0][0]);
            
            // sucesso, retorno a operadora
            if (isset($retorno[2])) {
                return ucwords(strip_tags(utf8_encode($retorno[2])));
            }
        }
    }

    /**
     * Remove arquivos temporários
     *
     * @access private
     * @return void
     */
    private function logout ()
    {
        // removendo o arquivo temporário do captcha
        if (file_exists($this->_captcha)) {
            unlink($this->_captcha);
        }
        
        // removendo cookies
        if (file_exists($this->_cookie)) {
            unlink($this->_cookie);
        }
    }
}
