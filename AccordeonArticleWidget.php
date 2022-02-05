<?php


namespace frontend\widgets;

use common\models\Article;
use Yii;
use yii\helpers\Html;

class AccordeonArticleWidget extends \yii\base\Widget
{
    public ?string $content;
    public ?string $currentUrl;
    public ?string $slug;
    public int $showNumbersContentMain;
    public int $showNumbersContentInner;

    public function run()
    {
        if ($this->currentUrl && $this->content){
            return Yii::$app->cache->getOrSet(Article::KEY_PREFIX_CACHE_VIEW_CONTENT . $this->slug . Yii::$app->language,function (){
                return $this->getList();
            });
        }
        return null;
    }

    private function getList()
    {
        $list = [];

        preg_match_all('/<h[2-9][^>]*?>(.*?)<\/h[2-9]>/si', $this->content, $matches);

        if (isset($matches[1]) && $matches[1]){
            $list[] = Html::a(Html::tag('h3',\Yii::t('frontend','Content')),'#',['id' => 'js-content-click']);
            $list[] = Html::tag('div',null,['class' => 'arrow-right']);//arrow-down
            $list[] = Html::beginTag('div',['id' => 'js-ul-article-view']);

            $prefixNumber = 0;
            $prefixNumberSub = null;
            $lastLevel = null;

            foreach ($matches[1] as $key => $match){
                $options = null;
                $options['data-title'] = strip_tags($match);
                $level = null;

                if (isset($matches[0][$key])){
                    $level = substr($matches[0][$key],2,1);

                    if ($this->showNumbersContentMain && $this->showNumbersContentInner){
                        if ($lastLevel && $level < $lastLevel && (int)$level >= 2){
                            for ($i = 6; $i > $level; $i--){
                                $prefixNumberSub[$i] = 0;
                            }
                        }
                        $lastLevel = $level;
                    }

                    if (is_numeric($level) && $level > 2){
                        $options['class'] = 'level-' . $level;
                        if ($this->showNumbersContentMain && $this->showNumbersContentInner){
                            if (!isset($prefixNumberSub[$level])){
                                $prefixNumberSub[$level] = 0;
                            }
                            ++$prefixNumberSub[$level];
                        }
                    }else{
                        if ($this->showNumbersContentMain){
                            ++$prefixNumber;
                            if ($this->showNumbersContentInner){
                                $prefixNumberSub[$level] = null;
                            }
                        }
                    }
                }

                $icon = Html::img('/img/article-arrow-right.svg');
                $textSub = null;
                $textPrefixNumber = null;

                if ($this->showNumbersContentMain){
                    if ($level <= 2 || $this->showNumbersContentInner){
                        $textPrefixNumber = $prefixNumber;
                        $match = '.&nbsp;' . $match;
                    }

                    if ($this->showNumbersContentInner){
                        $p = null;
                        if ($prefixNumberSub[$level]){
                            if ((int)$level === 4 && isset($prefixNumberSub[$level-1])){
                                $p = $prefixNumberSub[$level-1] . '.';
                            }
                            if ((int)$level === 5 && isset($prefixNumberSub[$level-2],$prefixNumberSub[$level-1])){
                                $p = $prefixNumberSub[$level-2] . '.' . $prefixNumberSub[$level-1] . '.';
                            }
                            if ((int)$level === 6 && isset($prefixNumberSub[$level-3],$prefixNumberSub[$level-2],$prefixNumberSub[$level-1])){
                                $p = $prefixNumberSub[$level-3] . '.' . $prefixNumberSub[$level-2] . '.' . $prefixNumberSub[$level-1] . '.';
                            }
                            $textSub = '.' . $p . $prefixNumberSub[$level];
                        }
                    }
                }

                $url = Html::a($icon . Html::tag('span',$textPrefixNumber . $textSub . $match),
                    $this->currentUrl . '#' . str_replace(' ','+',strip_tags($match)));
                $list[] = Html::tag('p',$url,$options);
            }
            $list[] = Html::endTag('div');
            return implode($list);
        }

        return null;
    }
}