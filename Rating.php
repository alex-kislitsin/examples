<?php
namespace common\components\ratingLeague;

use common\models\League;
use common\models\Matches;
use common\models\User;
use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\web\NotFoundHttpException;

class Rating extends Component
{
    public int $coefficient = 40;
    public int $startPoints = 1000;
    private int $ratingFirstPlayer;
    private int $ratingSecondPlayer;

    public function init()
    {
        if (isset(Yii::$app->keyStorage)) {
            $this->coefficient = Yii::$app->keyStorage->get('coefficient');
            $this->startPoints = Yii::$app->keyStorage->get('startPoints');
        }
        parent::init(); // TODO: Change the autogenerated stub
    }

    public function update(Matches $match)
    {
        if (!$match->user_id_win){
            return;
        }
        $this->ratingFirstPlayer = $match->firstPlayer->rating;
        $this->ratingSecondPlayer = $match->secondPlayer->rating;
        $this->updateFirst($match);
        $this->updateSecond($match);
    }

    public function reset(User $player): void
    {
        $player->updateAttributes(['rating' => $this->startPoints]);
    }

    private function updateFirst(Matches $match): void
    {
        $coefficient = $match->league->coefficient ?: $this->coefficient;
        $sa = (int)$match->first_player_id == (int)$match->user_id_win ? 1 : 0;
        $ra = $this->ratingFirstPlayer;
        $rb = $this->ratingSecondPlayer;
        $ea = 1 / (1 + pow(10, (($rb-$ra)/400)));
        $raNew = $ra + $coefficient * ($sa - $ea);

        if ($match->id){
            $match->updateAttributes([
                'points_1' => $ra,
                'points_reward_1' => round($raNew - $ra)
            ]);
        }

        $match->firstPlayer->updateAttributes(['rating' => round($raNew)]);
    }

    private function updateSecond(Matches $match): void
    {
        $coefficient = $match->league->coefficient ?: $this->coefficient;
        $sa = (int)$match->second_player_id == (int)$match->user_id_win ? 1 : 0;
        $ra = $this->ratingSecondPlayer;
        $rb = $this->ratingFirstPlayer;
        $ea = 1 / (1 + pow(10, (($rb-$ra)/400)));
        $raNew = $ra + $coefficient * ($sa - $ea);

        if ($match->id){
            $match->updateAttributes([
                'points_2' => $ra,
                'points_reward_2' => round($raNew - $ra)
            ]);
        }

        $match->secondPlayer->updateAttributes(['rating' => round($raNew)]);
    }

    public function reUpdate(int $leagueId): void
    {
        $allMatchesThisLeague = Matches::find()
            ->where(['league_id' => $leagueId])
            ->andWhere(['>','user_id_win',0])
            ->orderBy(['start_date' => SORT_ASC])
            ->active()
            ->all();

        if ($allMatchesThisLeague){
            $firstPlayersIds = ArrayHelper::getColumn($allMatchesThisLeague,'first_player_id');
            $secondPlayersIds = ArrayHelper::getColumn($allMatchesThisLeague,'second_player_id');
            $playersIds = ArrayHelper::merge($firstPlayersIds, $secondPlayersIds);
            $playersIds = array_unique($playersIds);

            if ($playersIds && $league = League::findOne(['id' => $leagueId])){
                $transaction = Yii::$app->db->beginTransaction();
                try {
                    User::updateAll(['rating' => $league->start_points ?: $this->startPoints],['in','id',$playersIds]);
                    foreach ($allMatchesThisLeague as $match){
                        $this->update($match);
                    }
                    $transaction->commit();
                } catch (\Exception $e) {
                    $transaction->rollback();
                }
            }
        }
    }

    public function updateRatingTable(int $leagueId): void
    {
        $players = User::find()
            ->active()
            ->isGamer()
            ->orderBy(['rating' => SORT_DESC])->all();

        if (!$league = League::findOne(['id' => $leagueId])){
            throw new NotFoundHttpException();
        }

        $league->updateAttributes([
            'data' => null
        ]);

        /* @var User $player */
        $tempArray = [];
        foreach ($players as $player) {

            $playerRatingTable = new \common\models\Rating();

            $matches = Matches::find()
                ->where(['or',['first_player_id' => $player->id],['second_player_id' => $player->id]])
                ->andWhere(['>','user_id_win',0])
                ->andWhere(['league_id' => $leagueId])
                ->orderBy(['start_date' => SORT_DESC])
                ->active()
                ->all();

            $actualRatingPeriod = false;

            if ($league->actual_rating_period &&
                (isset($matches[0]) && isset(($matches[0])->start_date) &&
                    date('Y-m-d',strtotime(($matches[0])->start_date . "+" . (int)$league->actual_rating_period . " days")) <
                    date('Y-m-d'))){
                $actualRatingPeriod = true;
            }

            if (!$matches || $actualRatingPeriod){
                continue;
            }

            $resultSeria = [];

            $win = 0;
            $loses = 0;
            $lastDateMatche = null;

            /* @var Matches $match */
            foreach ($matches as $key => $match){
                if ($key == 0 && isset($match->start_date)){
                    $lastDateMatche = date('Y-m-d',strtotime($match->start_date));
                }
                $result = $match->user_id_win == $player->id ? 1 : 0;

                if ($key < 10){
                    $resultSeria[] = [
                        'win' => $result
                    ];
                }
                if ($result == 0) $loses++;
                if ($result == 1) $win++;
            }

            $playerRatingTable->player_id = $player->id;
            $playerRatingTable->league_id = $leagueId;
            $playerRatingTable->rating = $player->rating ?: ($league->start_points ?: $this->startPoints);
            $playerRatingTable->matches = count($matches);
            $playerRatingTable->wins = $win;
            $playerRatingTable->loses = $loses;
            $playerRatingTable->series = $resultSeria;
            $playerRatingTable->last_match_date = $lastDateMatche;

            $tempArray[$playerRatingTable->rating] = $playerRatingTable;
        }

        if ($tempArray){
            krsort($tempArray);

            $pos = 1;
            /* @var \common\models\Rating $rating */
            foreach ($tempArray as $rating){
                $rating->position = $pos;
                $pos++;
            }

            $league->updateAttributes([
                'data' => Json::encode(array_values(ArrayHelper::toArray($tempArray)))
            ]);
        }
    }
    public function updateAll(int $userId)
    {
        $subQuery = Matches::find()->select('league_id')->where(['or',['first_player_id' => $userId],['second_player_id' => $userId]])->active();

        if ($leagues = \common\models\League::find()->where(['id' => $subQuery])->all()){
            User::updateAll(['rating' => 0]);
            /* @var League $league */
            foreach ($leagues as $league){
                if (isset(\Yii::$app->ratingLeague)) {
                    \Yii::$app->ratingLeague->reUpdate($league->id);
                }
            }
            foreach ($leagues as $league){
                if (isset(\Yii::$app->ratingLeague)) {
                    \Yii::$app->ratingLeague->updateRatingTable($league->id);
                }
            }
        }
    }
}