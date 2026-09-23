<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Custom Pages - Language pack (Brazilian Portuguese)
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accesslevel'] = 'Capacidade obrigatória';
$string['accesslevel_help'] = 'Lista opcional de capacidades do Moodle, separadas por vírgula, que restringem ainda mais quem pode ver esta página (além da situação, das datas e de "somente usuários autenticados"). <strong>A avaliação ocorre da esquerda para a direita, com resultado do tipo OU:</strong> cada entrada só é aplicada enquanto o acesso ainda não tiver sido concedido; o nome simples de uma capacidade concede acesso a quem a possui; um nome precedido de <strong>!</strong> concede acesso a quem <em>não</em> a possui. Assim que uma entrada concede acesso, as demais são ignoradas — a ordem importa quando regras positivas e negadas são misturadas. Exemplo: <code>moodle/course:view, !moodle/site:config</code> libera qualquer usuário com permissão de ver o curso que não seja administrador geral do site. Deixe em branco para não aplicar nenhuma restrição adicional por capacidade.';
$string['accesslevel_negationonly'] = 'A capacidade obrigatória não pode ser formada apenas por entradas negadas. Uma regra como <code>!moodle/site:config</code> concede a página a todo visitante que <em>não</em> possui a capacidade, inclusive visitantes anônimos, e portanto não restringe nada. Acrescente uma capacidade positiva junto da negação ou defina "Somente usuários autenticados" como "Sim".';
$string['accesslevel_unknowncapability'] = 'A capacidade \'{$a}\' não existe neste site. Verifique a grafia e use o nome completo, como <code>moodle/site:config</code>.';
$string['addpage'] = 'Adicionar nova página';
$string['backtolist'] = 'Voltar à lista de páginas';
$string['categorymovebusy'] = 'As páginas personalizadas desta categoria não puderam ser movidas, porque outra página está sendo salva neste momento. Tente novamente em instantes.';
$string['categorypages'] = 'Páginas personalizadas';
$string['confirmdeletepage'] = 'Tem certeza de que deseja excluir a página \'{$a}\'?';
$string['contenthtml'] = 'HTML de conteúdo';
$string['contenthtml_description'] = 'Conteúdo HTML bruto';
$string['contenthtml_description_help'] = 'Informe o conteúdo HTML bruto que será exibido diretamente na página. Esse conteúdo não passa pelo editor e é renderizado exatamente como foi digitado. Use com cautela, pois pode afetar o layout e a segurança da página. Em uma página que pertence a uma categoria de cursos, o bloco é limpo como qualquer outro conteúdo, a menos que o autor tenha permissão para gravar HTML sem limpeza neste site.';
$string['contenthtml_placeholder'] = 'Informe aqui o conteúdo HTML bruto...';
$string['custompage_title'] = 'Gerenciamento de páginas';
$string['delete'] = 'Remover';
$string['edit_head'] = 'Conteúdo para &lt;head&gt;';
$string['edit_ogimage'] = 'Arquivo de imagem Open Graph';
$string['edit_ogimage_notimage'] = 'Este arquivo não é uma imagem JPEG, PNG ou WebP do tipo que o nome indica. A imagem Open Graph é servida a qualquer pessoa, então só a própria imagem é aceita: envie a figura, salva com a extensão do seu formato.';
$string['form_field_date'] = 'Data de início da publicação';
$string['form_field_enddate'] = 'Data de fim da publicação';
$string['form_field_enddate_description'] = 'Data de fim da publicação';
$string['form_field_enddate_description_help'] = 'Selecione a data em que esta página será despublicada — uma data no passado restringe o acesso até essa data.';
$string['hidetitle'] = 'Ocultar título';
$string['menu_name'] = 'URL amigável';
$string['menu_name_description'] = 'Descrição da URL amigável';
$string['menu_name_description_help'] = 'Informe o identificador de URL da página (apenas letras, números, hífen e sublinhado; caracteres inválidos são removidos ao salvar). As regras de reescrita do servidor web precisam direcionar requisições como <strong>sobre-nos</strong> ao visualizador deste plugin, caso sejam usadas URLs na raiz do site.';
$string['menuname_reserved'] = 'Esta URL amigável é reservada pelo próprio Moodle. Endereços como <code>login</code>, <code>course</code>, <code>pluginfile</code> ou qualquer um iniciado por um tipo de plugin, como <code>local_</code>, são atendidos pelo site: uma página que ocupasse um deles nunca seria alcançada ou esconderia parte do Moodle. Escolha uma diferente.';
$string['menuname_taken'] = 'Esta URL amigável já está em uso por outra página. Escolha uma diferente.';
$string['metaauthor'] = 'Meta autor';
$string['metaauthor_description'] = 'Descrição do meta autor';
$string['metaauthor_description_help'] = 'Informe o meta autor da página. Ele será usado para identificar o autor da página.';
$string['metadescription'] = 'Metadescrição';
$string['metadescription_description'] = 'Descrição da metadescrição';
$string['metadescription_description_help'] = 'Informe a metadescrição da página. Ela será usada para descrever a página aos mecanismos de busca.';
$string['metakeywords'] = 'Metapalavras-chave';
$string['metakeywords_description'] = 'Descrição das metapalavras-chave';
$string['metakeywords_description_help'] = 'Informe as metapalavras-chave da página. Elas serão usadas para descrever a página aos mecanismos de busca.';
$string['metarobots'] = 'Meta robots';
$string['metarobots_description'] = 'Descrição do meta robots';
$string['metarobots_description_help'] = 'Informe a metatag robots da página. Ela será usada para controlar como os mecanismos de busca indexam a página. As opções disponíveis incluem:<br />
<ul>
    <li>"index": permite a indexação da página.</li>
    <li>"noindex": impede a indexação da página.</li>
    <li>"follow": permite que os links da página sejam seguidos.</li>
    <li>"nofollow": impede que os links da página sejam seguidos.</li>
    <li>"noarchive": impede que os mecanismos de busca mantenham a página em cache.</li>
    <li>"nosnippet": impede que os mecanismos de busca exibam um trecho da página nos resultados.</li>
    <li>"noodp": impede o uso dos dados do Open Directory Project (DMOZ) para a página.</li>
    <li>"notranslate": impede que os mecanismos de busca ofereçam tradução da página.</li>
    <li>"noimageindex": impede que os mecanismos de busca indexem as imagens da página.</li>
</ul>';
$string['metatitle'] = 'Metatítulo';
$string['metatitle_description'] = 'Descrição do metatítulo';
$string['metatitle_description_help'] = 'Informe o metatítulo da página. Ele será usado para exibir o título da página nos resultados de busca.';
$string['noaccess'] = 'Sem permissão para ver esta página.';
$string['none'] = 'Nenhuma';
$string['onlyloggedin'] = 'Somente usuários autenticados';
$string['onlyloggedin_description'] = 'Exibir a página apenas para usuários autenticados';
$string['onlyloggedin_description_help'] = '<ul>
    <li>Com a opção "Sim", a página fica visível somente para usuários autenticados.</li>
    <li>Com a opção "Não", a página fica visível para todos os usuários.</li>
    <li>Usuários não autenticados veem uma mensagem informando que a página é visível somente para usuários autenticados.</li>
    <li>Usuários visitantes veem uma mensagem informando que a página é visível somente para usuários autenticados.</li>
</ul>';
$string['onlyloggedin_publishlocked'] = 'Publicar para visitantes não autenticados exige a permissão \'Publicar uma página de categoria para visitantes não autenticados\' (<code>local/page:publishcategorypages</code>) nesta categoria de cursos, que você não possui aqui. A página é salva apenas para usuários autenticados.';
$string['page:addpages'] = 'Adicionar e editar páginas personalizadas do site (HTML confiável, metadados brutos de cabeçalho e HTML de conteúdo — declarada como RISK_XSS). O papel padrão de criador de curso pode exercê-la em todo o site; atribua-a apenas a papéis que devam poder injetar marcação arbitrária para todos os visitantes.';
$string['page:managecategorypages'] = 'Criar e editar as páginas personalizadas que pertencem a uma categoria de cursos. Quem tem esta permissão edita páginas apenas nas categorias em que ela foi concedida; as páginas do site continuam sob \'Adicionar e editar páginas personalizadas do site\'. Declarada como RISK_SPAM porque a página carrega texto escrito pelo autor.';
$string['page:publishcategorypages'] = 'Publicar uma página de categoria para visitantes não autenticados. Propositalmente separada da edição: escrever uma página e colocá-la diante da web aberta são atos diferentes, e um papel pode merecer confiança para um e não para o outro. Declarada agora e aplicada pela verificação de publicação que vem a seguir.';
$string['page_content_description'] = 'Informe aqui o conteúdo da página.';
$string['page_name'] = 'Título da página';
$string['pagedate_description'] = 'Selecione a data em que esta página será publicada — uma data no futuro restringe o acesso até essa data.';
$string['pagedate_description_help'] = 'Selecione a data de publicação desta página — o acesso fica restrito até a data indicada.';
$string['pagename_placeholder'] = 'Informe o nome da página';
$string['pagenotfound'] = 'A página solicitada não existe ou foi excluída.';
$string['pagesetup_heading'] = 'Configuração de páginas';
$string['pagesetup_title'] = 'Configuração de páginas';
$string['pluginname'] = 'Páginas personalizadas';
$string['pluginsettings'] = 'Configurações do plugin';
$string['pluginsettings_managepages'] = 'Configurações do gerenciamento de páginas';
$string['privacy:metadata'] = 'O plugin de páginas personalizadas não armazena nenhum dado pessoal.';
$string['restricted'] = 'Restrita por data';
$string['setting_additionalhead'] = 'Habilitar HTML adicional no cabeçalho';
$string['setting_additionalhead_description'] = 'Permitir que conteúdo personalizado seja acrescentado à seção &lt;head&gt; do HTML.';
$string['shareurl'] = 'Endereço curto para compartilhar';
$string['status'] = 'Situação';
$string['status_archived'] = 'Arquivada';
$string['status_draft'] = 'Rascunho';
$string['status_live'] = 'Publicada';
