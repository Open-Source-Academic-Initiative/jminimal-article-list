<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\Component\Content\Site\Helper\RouteHelper;

defined('_JEXEC') or die;

class PlgContentRelatedminimal extends \Joomla\CMS\Plugin\CMSPlugin
{
    public function onContentPrepare($context, &$article, &$params, $limitstart = 0)
    {
         // Only apply to articles
         /*if ($context !== 'com_content.article') {
             return;
    }*/

         // Check if article has a category ID
        if (empty($article->catid)) {
             return;
         }

         // Get allowed categories from plugin params
         $targetCategory = (int) $this->params->get('category_id');

         // If current article category not in allowed list, skip
         if ($article->catid !== $targetCategory) {
             return;
         }

         // Get the custom HTML + JS from the plugin parameters
         $articleList = $this->buildRelatedList($article);

         // Append the custom code at the end of the article
         if (!empty($articleList)) {
             $article->text .= "\n<div class='custom-related-article-list'>{$articleList}</div>";
         }

    }

    protected function buildRelatedList($article)
    {
        $targetCategory = (int) $this->params->get('category_id');
        if ($targetCategory && (int) $article->catid !== $targetCategory) {
            return '';
        }

        $limit = (int) $this->params->get('limit', 5);
        $useKeywords = (bool) $this->params->get('use_keywords', 1);

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(['id','title','metakey','metadesc'])
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('catid') . ' = ' . (int) $article->catid)
            ->where($db->quoteName('id') . ' <> ' . (int) $article->id);

        if ($useKeywords) {
            $terms = [];

            if (!empty($article->metakey)) {
                $terms = array_merge($terms, array_map('trim', explode(',', $article->metakey)));
            }
            if (!empty($article->metadesc)) {
                $terms = array_merge($terms, preg_split('/\s+/', strip_tags($article->metadesc)));
            }

            $terms = array_filter(array_unique($terms));

            if ($terms) {
                $likeClauses = [];

                foreach ($terms as $t) {
                    $t = trim($t);
                    if ($t === '') continue;

                    $quoted = $db->quote('%' . $db->escape($t, true) . '%', false);
                    $likeClauses[] = '(' . $db->quoteName('metakey') . ' LIKE ' . $quoted .
                                     ' OR ' . $db->quoteName('metadesc') . ' LIKE ' . $quoted . ')';
                }

                if ($likeClauses) {
                    $query->where('(' . implode(' OR ', $likeClauses) . ')');
                }
            }
        }

        $query->order('RAND()');

        $db->setQuery($query, 0, $limit);
        $items = $db->loadObjectList();

        if (!$items) {
            return '';
        }

        $html = '<div class="related-minimal"><h4>También te puede interesar...</h4><ul>';

        foreach ($items as $item) {
            $link = Route::_(RouteHelper::getArticleRoute((int)$item->id, (int)$article->catid));
            $title = htmlspecialchars($item->title, ENT_QUOTES, 'UTF-8');
            $html .= '<li><a href="' . $link . '">' . $title . '</a></li>';
        }

        $html .= '</ul></div>';

        return $html;
    }
}
