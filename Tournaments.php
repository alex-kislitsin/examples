<?php

namespace common\models;

use common\components\ratingLeague\events\ChangeResultsMatchesEvent;
use common\models\query\TournamentsQuery;
use common\services\ImageService;
use DateTime;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

/**
 * This is the model class for table "tournaments".
 *
 * @property int $id
 * @property string|null $start_date
 * @property int|null $max_players
 * @property int|null $age_category_id
 * @property array $galleries
 *
 * @property AgeCategory $ageCategory
 * @property User[] $profiles
 * @property TournamentsHasProfiles[] $tournamentsHasProfiles
 * @property TournamentsHasProfiles $tournamentWinProfile
 * @property TournamentHasPlayersDouble $tournamentWinDouble
 * @property TournamentsHasProfiles $inTournamentHasProfile
 * @property TournamentStage[] $stages
 * @property TournamentStageGames[] $games
 * @property TournamentGallery[] $tournamentGallery
 * @property TournamentGallery[] $tournamentGalleryFront
 *
 * @property string|null $title
 * @property string|null $url_video
 * @property string|null $end_date
 * @property int $type
 * @property int|null $cost
 * @property string|null $description
 * @property int|null $league_id
 * @property int $late_request_at
 * @property int|null $sportclub_id
 * @property int|null $status
 * @property string|null $deleted_at
 * @property int|null $age_limit
 * @property int|null $not_show_gallery
 * @property mixed|null $use_stages
 *
 * @property TournamentHasPlayersDouble[] $tournamentsHasDoubles
 * @property PlayersDouble[] $doubles
 * @property SportclubCourt[] $courts
 * @property League $league
 * @property Sportclub $club
 * @property RequestTournament[] $requests
 * @property RequestTournament $inRequestList
 * @property TournamentStageGames[] $useStagesGames
 */
class Tournaments extends ActiveRecord
{
    public const STATUS_ACTUAL = 1;
    public const STATUS_FINISHED = 2;
    public const STATUS_DELETE = 0;

    public const TYPE_TOURNAMENT_ONE = 0;
    public const TYPE_TOURNAMENT_DOUBLE = 1;

    public $regulations_id = null;

    /**
     * @var array
     */
    public $galleries;

    /**
     * @return array|mixed
     */
    public static function statuses()
    {
        return [
            self::STATUS_ACTUAL => 'Актуально',
            self::STATUS_FINISHED => 'Завершено',
            self::STATUS_DELETE => 'Удалено',
        ];
    }

    /**
     * @return array|mixed
     */
    public static function types()
    {
        return [
            self::TYPE_TOURNAMENT_ONE => 'Одиночный',
            self::TYPE_TOURNAMENT_DOUBLE => 'Парный'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tournaments';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['start_date','age_category_id','type','sportclub_id'], 'required'],
            [['max_players', 'age_category_id','sportclub_id','type','regulations_id','league_id','cost','status','age_limit'], 'integer'],
            [['age_category_id'], 'exist', 'skipOnError' => true, 'targetClass' => AgeCategory::class, 'targetAttribute' => ['age_category_id' => 'id']],
            [['start_date'],'filter','filter' => function($value){
                return date('Y-m-d H:i:0', strtotime($value));
            }],
            [['end_date'],'filter','filter' => function($value){
                return $value ? date('Y-m-d', strtotime($value)) : null;
            }],
            [['late_request_at'],'filter','filter' => function($value){
                return $value ? date('Y-m-d H:i:0', strtotime($value)) : null;
            }],
            [['deleted_at'],'filter','filter' => function($value){
                return $value ? date('Y-m-d H:i:0', strtotime($value)) : null;
            }],
            [['age_limit'],'filter','filter' => function($value){
                return $value ?: null;
            }],
            ['title','string','max' => 100],
            [['start_date','url_video','end_date','description','deleted_at','late_request_at'],'string'],
            [['title','url_video','end_date','cost','description','league_id','sportclub_id','late_request_at','deleted_at','age_limit','not_show_gallery','use_stages'],'default','value' => null],
            [['status'],'default','value' => self::STATUS_ACTUAL],
            ['max_players','default','value' => 0],
            ['max_players',function($attribute){
                if ($this->max_players && $this->max_players < count($this->profiles)){
                    $this->addError($attribute, 'Максимальное кол-во участников не может быть меньше из реального кол-ва');
                    return;
                }
            }],
            ['start_date',function(){
                if ($this->end_date && strtotime($this->start_date) > strtotime($this->end_date)){
                    $this->addError('end_date', 'Дата окончания не может быть меньше даты начала турнира');
                    return;
                }
                if ($this->age_category_id && $this->league_id &&
                    !League::find()->where(['age_category_id' => $this->age_category_id,'id' => $this->league_id])->count()){
                    $this->addError('age_category_id', 'Выберите разные мяч и лигу');
                    $this->addError('league_id', 'Выберите разные мяч и лигу');
                }
                if (!$this->league_id){
                    $this->league_id = null;
                }
                if ($this->url_video){
                    $this->url_video = $this->getVideoIdByUrl();
                }
            }],
            [['court_ids','use_stages'], 'filter', 'filter' => function($v) {
                return empty($v) || !is_array($v)? null : $v;
            }],
            [['galleries', 'court_ids','use_stages'], 'safe'],
            [['type'], 'default', 'value' => self::TYPE_TOURNAMENT_ONE],
            [['type'], 'in', 'range' => array_keys(self::types())],
            [['status'], 'in', 'range' => array_keys(self::statuses())],
            [['league_id'], 'exist', 'skipOnError' => true, 'targetClass' => League::class, 'targetAttribute' => ['league_id' => 'id']],
            [['not_show_gallery'], 'boolean'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID Турнира',
            'start_date' => 'Дата турнира',
            'max_players' => 'Кол-во участников / макс.',
            'age_category_id' => 'Категория',
            'title' => 'Название',
            'url_video' => 'Ссылка на видео турнира',
            'end_date' => 'Дата окончания турнира',
            'galleries' => 'Галерея',
            'sportclub_id' => 'Место проведения',
            'type' => 'Тип турнира',
            'cost' => 'Стоимость участия',
            'description' => 'Описание',
            'league_id' => 'Лига',
            'late_request_at' => 'Дата и время поздней заявки',
            'status' => 'Статус',
            'deleted_at' => 'Дата удаления',
            'age_limit' => 'Возрастное ограничение',
            'not_show_gallery' => 'Не показывать на странице турнира',
            'use_stages' => 'Этапы турнира',
        ];
    }

    /**
     * {@inheritdoc}
     * @return TournamentsQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new TournamentsQuery(get_called_class());
    }

    /**
     * Gets query for [[AgeCategory]].
     *
     * @return ActiveQuery
     */
    public function getAgeCategory()
    {
        return $this->hasOne(AgeCategory::className(), ['id' => 'age_category_id']);
    }

    public function getClub(): ActiveQuery
    {
        return $this->hasOne(Sportclub::class, ['id' => 'sportclub_id']);
    }

    /**
     * Gets query for [[Profiles]].
     *
     * @return ActiveQuery
     */
    public function getProfiles()
    {
        return $this->hasMany(User::className(), ['id' => 'profile_id'])
            ->viaTable('tournaments_has_profiles', ['tournament_id' => 'id'])
            ->orderBy(['user.first_name' => SORT_ASC]);
    }

    /**
     * Gets query for [[TournamentsHasProfiles]].
     *
     * @return ActiveQuery
     */
    public function getTournamentsHasProfiles()
    {
        return $this->hasMany(TournamentsHasProfiles::className(), ['tournament_id' => 'id']);
    }

    /**
     * Gets query for [[TournamentHasPlayersDouble]].
     *
     * @return ActiveQuery
     */
    public function getTournamentsHasDoubles()
    {
        return $this->hasMany(TournamentHasPlayersDouble::className(), ['tournament_id' => 'id']);
    }

    /**
     * Gets query for [[TournamentsHasProfiles]].
     *
     * @return ActiveQuery
     */
    public function getInTournamentHasProfile()
    {
        return $this->hasOne(TournamentsHasProfiles::className(), ['tournament_id' => 'id'])
            ->where(['profile_id' => Yii::$app->user->identity->id ?? 0]);
    }

    /**
     * Gets query for [[TournamentsHasProfiles]].
     *
     * @return ActiveQuery
     */
    public function getTournamentWinProfile()
    {
        return $this->hasOne(TournamentsHasProfiles::className(), ['tournament_id' => 'id'])->where(['position' => 1]);
    }

    /**
     * Gets query for [[TournamentHasPlayersDouble]].
     *
     * @return ActiveQuery
     */
    public function getTournamentWinDouble()
    {
        return $this->hasOne(TournamentHasPlayersDouble::className(), ['tournament_id' => 'id'])->where(['position' => 1]);
    }

    /**
     * Gets query for [[TournamentStage]].
     *
     * @return ActiveQuery
     */
    public function getStages()
    {
        return $this->hasMany(TournamentStage::className(), ['tournament_id' => 'id'])->orderBy(['tournament_stage.sort' => SORT_DESC]);
    }

    public function getUseStagesGames(): ActiveQuery
    {
        return $this->hasMany(TournamentStageGames::class, ['tournament_id' => 'id'])->andWhere(['is not','tournament_stage_games.use_stage_id',null]);
    }

    public function getGames()
    {
        return $this->hasMany(TournamentStageGames::class, ['tournament_id' => 'id']);
    }

    /**
     * Gets query for [[SportclubCourt]].
     *
     * @return ActiveQuery
     */
    public function getCourts()
    {
        return $this->hasMany(SportclubCourt::className(), ['id' => 'court_ids']);
    }

    /**
     * Gets query for [[TournamentGallery]].
     *
     * @return ActiveQuery
     */
    public function getTournamentGallery()
    {
        return $this->hasMany(TournamentGallery::className(), ['tournament_id' => 'id'])->orderBy(['tournament_gallery.order' => SORT_ASC]);
    }

    public function getTournamentGalleryFront()
    {
        return $this->hasMany(TournamentGallery::className(), ['tournament_id' => 'id'])->orderBy(['tournament_gallery.order' => SORT_DESC]);
    }

    /**
     * Gets query for [[TournamentGallery]].
     *
     * @return ActiveQuery
     */
    public function getRequests(): ActiveQuery
    {
        return $this->hasMany(RequestTournament::class, ['tournament_id' => 'id'])
            ->orderBy(['request_tournament.created_at' => SORT_DESC]);
    }

    /**
     * Gets query for [[TournamentHasPlayersDouble]].
     *
     * @return ActiveQuery
     */
    public function getInRequestList(): ActiveQuery
    {
        return $this->hasOne(RequestTournament::class, ['tournament_id' => 'id'])
            ->where(['request_tournament.user_id' => Yii::$app->user->identity->id ?? 0]);
    }

    /**
     * Gets query for [[PlayersDouble]].
     *
     * @return ActiveQuery
     */
    public function getDoubles()
    {
        return $this->hasMany(PlayersDouble::className(), ['id' => 'players_double_id'])
            ->viaTable('tournament_has_players_double', ['tournament_id' => 'id']);
    }

    public function showDate()
    {
        return $this->end_date ?
            (date('d.m', strtotime($this->start_date)) . ' - ' . date('d.m', strtotime($this->end_date))) :
            date('H:i', strtotime($this->start_date));
    }

    public function showDateResults()
    {
        return $this->end_date ?
            (date('d.m', strtotime($this->start_date)) . ' - ' . date('d.m', strtotime($this->end_date))) :
            (date('j', strtotime($this->start_date)) . ' ' .
            Yii::$app->formatter->asDate(strtotime($this->start_date), 'php:F') . ' '. date('H:i', strtotime($this->start_date)));
    }

    public function showDateResultsSelect(self $tournament): string
    {
        return $tournament->end_date ?
            (date('d.m', strtotime($tournament->start_date)) . ' - ' . date('d.m', strtotime($tournament->end_date))) :
            (date('j', strtotime($tournament->start_date)) . ' ' .
                Yii::$app->formatter->asDate(strtotime($tournament->start_date), 'php:F') . ' '. date('H:i', strtotime($tournament->start_date)));
    }

    public function showDateInfoBlock($text = null, bool $late_request_at = false)
    {
        $result = Yii::t('frontend',$text);

        $date = $this->start_date;

        if ($late_request_at){
            if ($this->late_request_at){
                $date = $this->late_request_at;
            }else{
                $result = Yii::t('frontend','VIEW_TOURNAMENT_TEXT_GAMERS_WAITING_UP');
            }
        }

        $month = Yii::$app->formatter->asDate(strtotime($date), 'MMM');
        $month = str_replace('.',',',$month);

        $mainDate = date('j', strtotime($date)) . ' ' . $month . ' '. date('H:i', strtotime($date));

        $result .= ' ' . $mainDate;

        return $result;
    }

    public function isLate(): bool
    {
        return $this->late_request_at && date('Y-m-d H:i:00') < $this->late_request_at;
    }

    public function isLateEnd(): bool
    {
        return $this->late_request_at && date('Y-m-d H:i:00') >= $this->late_request_at;
    }

    public function showDateResultsNotTime()
    {
        return $this->end_date ?
            (date('d.m', strtotime($this->start_date)) . ' - ' . date('d.m', strtotime($this->end_date))) :
            (date('j', strtotime($this->start_date)) . ' ' .
                Yii::$app->formatter->asDate(strtotime($this->start_date), 'php:F') . ' '. date('H:i', strtotime($this->start_date)));
    }

    public static function getDataproviderProfiles(int $tourId)
    {
        return new ActiveDataProvider([
            'query' => TournamentsHasProfiles::find()
                ->select(['tournament_id','profile_id','Ifnull(position, 9999) as position'])
                ->with('profile')
                ->where(['tournament_id' => $tourId])->orderBy(['position' => SORT_ASC]),
            'sort' => false,
            'pagination' => false
        ]);
    }

    public static function getDataproviderDoubles(int $tourId)
    {
        return new ActiveDataProvider([
            'query' => TournamentHasPlayersDouble::find()
                ->select(['tournament_id','players_double_id','Ifnull(position, 9999) as position'])
                ->with('playersDouble')
                ->where(['tournament_id' => $tourId])->orderBy(['position' => SORT_ASC]),
            'sort' => false,
            'pagination' => false
        ]);
    }

    public static function getDataproviderRequest(int $tourId)
    {
        return new ActiveDataProvider([
            'query' => RequestTournament::find()
                ->with('user')
                ->where(['tournament_id' => $tourId])->orderBy(['request_tournament.created_at' => SORT_DESC]),
            'sort' => false,
            'pagination' => false
        ]);
    }

    public function getListInvited()
    {
        $ids = ArrayHelper::getColumn(ListInvitedPlayers::find()->select('player_id, max(created_at) as created_at, max(id) as id')->andWhere(['tournament_id' => $this->id])
            ->groupBy('player_id')->all(),'id');

        $players = [];
        if ($this->tournamentsHasProfiles){
            $players = ArrayHelper::getColumn($this->tournamentsHasProfiles,'profile_id');
        }

        $query = ListInvitedPlayers::find()->where(['id' => $ids]);
        if ($players){
            $query->andWhere(['not in','player_id',$players]);
        }

        $listInvited = $query->all();

        /* @var ListInvitedPlayers $item */
        $dataArray = [];
        foreach ($listInvited as $item){
            $dataArray[$item->player_id] = $item;
        }

        return $dataArray;
    }

    public function getListInvitedFrontend()
    {
        $ids = ArrayHelper::getColumn(ListInvitedPlayers::find()->select('player_id, max(created_at) as created_at, max(id) as id')->andWhere(['tournament_id' => $this->id])
            ->groupBy('player_id')->all(),'id');

        $players = [];
        if ($this->tournamentsHasProfiles){
            $players = ArrayHelper::getColumn($this->tournamentsHasProfiles,'profile_id');
        }

        $query = ListInvitedPlayers::find()->with('player')->where(['id' => $ids]);
        if ($players){
            $query->andWhere(['not in','player_id',$players]);
        }

        return $query->all();
    }

    public function getUsersForInvite($listInvited = [])
    {
        $query = User::find()
            ->with('ageCategory')
            ->isGamer()
            ->active();

        if (!Yii::$app->user->isGuest){
            $query->andWhere(['!=','id', Yii::$app->user->identity->id]);
        }

        if ($this->age_limit){
            $query->andWhere(['>=','birth_date',date('Y-m-d',strtotime('-' . $this->age_limit . 'years'))]);
        }

        if ($listInvited){
            $query->orderBy([new Expression('field (id, ' . implode(',',array_keys($listInvited)) . ') desc')]);
        }

        return $query->all();
    }

    public function getGallery()
    {
        $initialPreview = [];
        $initialPreviewConfig = [];

        foreach ($this->tournamentGallery as $gallery){
            $initialPreview[] = Yii::$app->urlManagerStorage->hostInfo . '/img:h=159.24,f=sd' . ImageService::PATH_RENDER . $gallery->path;
            $initialPreviewConfig[] = [
                'caption' => $gallery->name,
                'size' => $gallery->size,
                'url' => Url::to(['tournaments/delete-image','id' => $gallery->id]),
                'key' => $gallery->id,
                'extra' => [
                    'id' => $gallery->id
                ],
                'zoomData' => Yii::$app->urlManagerStorage->hostInfo . ImageService::PATH_RENDER . $gallery->path,
            ];
        }

        return [
            'initialPreview' => $initialPreview,
            'initialPreviewConfig' => $initialPreviewConfig,
        ];
    }

    public function getCountMatches(): int
    {
        $count = 0;
        if ($this->stages){
            foreach ($this->stages as $stage){
                $count += count($stage->tournamentStageGamesWin);
            }
        }
        return $count;
    }

    public function isDouble(): bool
    {
        return $this->type === self::TYPE_TOURNAMENT_DOUBLE;
    }

    public function getLive(): bool
    {
        foreach ($this->courts as $court) {
            if ($court->hasVideo()) {
                return true;
            }
        }
        return false;
    }

    public static function getAllUseLeague(): array
    {
        $result = [];
        $subQuery = TournamentsHasProfiles::find()->select('tournament_id')->where(['is not','position', null])->distinct();
        /* @var self $item */
        foreach (self::find()
                     ->where(['is not','league_id',null])
                     ->andWhere(['type' => self::TYPE_TOURNAMENT_ONE])
                     ->andWhere(['<','start_date',date('Y-m-d H:i:s')])
                     ->andWhere(['not in','tournaments.id', $subQuery])
                     ->active()
                     ->orderBy(['league_id' => SORT_ASC])
                     ->all() as $item){
            $result[] = $item;
        }
        return $result;
    }

    public function isGo()
    {
        return $this->start_date <= date('Y-m-d H:i:s') && ($this->end_date ? $this->end_date > date('Y-m-d H:i:s') : true);
    }

    public function isFinished()
    {
        return $this->start_date <= date('Y-m-d H:i:s') && ($this->tournamentWinProfile || $this->tournamentWinDouble);
    }

    public function isNotstarted()
    {
        return $this->start_date > date('Y-m-d H:i:s');
    }

    public function getCortsArray(): array
    {
        $courts = [];
        foreach ($this->courts as $court) {
            if ($court->hasVideo()) {
                $courts[] = $court;
            }
        }
        return $courts;
    }

    public function isMaxLimit(): bool
    {
        if ($this->type === self::TYPE_TOURNAMENT_ONE){
            return $this->max_players && $this->max_players <= count($this->profiles);
        }
        return $this->max_players && $this->max_players <= count($this->doubles) * 2;
    }

    public function getVideoIdByUrl(){
        $url = $this->url_video;
        if(!preg_match('/([\/|\?|&]vi?[\/|=]|youtu\.be\/|embed\/)([a-zA-Z0-9_-]+)/ims', $url, $matches)){
            return false;
        }
        return isset($matches[2]) ? 'https://www.youtube.com/embed/' . $matches[2] : null;
    }

    public function getOldTournaments(): array
    {
        $result = [];
        /* @var self $item */
        foreach (self::find()->active()->joinWith('tournamentsHasProfiles')
                     ->joinWith('tournamentsHasDoubles')
                     ->andWhere(['or',['tournaments_has_profiles.position' => 1],['tournament_has_players_double.position' => 1]])
                     ->orderBy(['start_date' => SORT_DESC])->all() as $item){
            $result[$item->id] = $item->title . ' (' . $this->showDateResultsSelect($item) . ')';
        }
        return $result;
    }

    public function afterHidden(): void
    {
        if ($matches = Matches::find()->where(['tournament_id' => $this->id])->active()->all()){
            Matches::updateAll(['status' => Matches::STATUS_DELETE],['tournament_id' => $this->id]);
            $this->updateRating($matches);
        }
    }

    public function afterRecovery(): void
    {
        if ($matches = Matches::find()->where(['tournament_id' => $this->id])->delete()->all()){
            Matches::updateAll(['status' => Matches::STATUS_ACTUAL],['tournament_id' => $this->id]);
            $this->updateRating($matches);
        }
    }

    private function updateRating($matches): void
    {
        /* @var Matches $match */
        $leagues = [];
        foreach ($matches as $match){
            if ($match->league_id && !in_array($match->league_id,$leagues)){
                $match->trigger(Matches::EVENT_AFTER_RE_CHANGE_RESULTS, new ChangeResultsMatchesEvent(['match' => $match]));
                $leagues[] = $match->league_id;
            }
        }
    }

    public function ageLimitAccess(string $birthDate): bool
    {
        if ($birthDate && $this->age_limit){
            try {
                $born = new DateTime($birthDate);
                $age = $born->diff(new DateTime)->format('%y');
                if ($age > $this->age_limit){
                    return true;
                }
            } catch (\Exception $e) {
            }
        }
        return false;
    }

    public function showGallery(): bool
    {
        if ($this->not_show_gallery){
            return false;
        }

        $dateUse = date('Y-m-d H:i:00',strtotime("+ 3 days",strtotime($this->end_date ?: $this->start_date)));

        if (date('Y-m-d H:i:00') >= $dateUse){

            $lastDateUploadImages = (new Query())
                ->from(TournamentGallery::tableName())
                ->select('created_at')
                ->where(['tournament_id' => $this->id])
                ->orderBy(['created_at' => SORT_DESC])->scalar();

            if (!$lastDateUploadImages) {
                return false;
            }
            if ($lastDateUploadImages > $dateUse) {
                return false;
            }
        }

        return true;
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)){
            if (!$this->isNewRecord){

                $allStages = ArrayHelper::map(Stage::find()->all(),'id','name');
                $oldStages = $this->getOldAttribute('use_stages');
                $useStagesGames = TournamentStageGames::find()->select('use_stage_id')->where(['tournament_stage_id' => $this->id])
                    ->andWhere(['is not','use_stage_id',null])->distinct(true)->column();
                $stagesNotDelete = null;

                if ($oldStages && is_array($oldStages)){
                    foreach ($oldStages as $stageId){
                        if (in_array($stageId,$useStagesGames) && !in_array($stageId,$this->use_stages)){
                            $stagesNotDelete[] = $allStages[$stageId];
                        }
                    }
                }
                if ($stagesNotDelete){
                    if (count($stagesNotDelete) > 1){
                        Yii::$app->session->setFlash('error', 'Этапы: ' .
                            implode(', ',$stagesNotDelete) . ', не могут быть удалены, т.к. они участвуют в играх этого турнира!');
                    }else{
                        Yii::$app->session->setFlash('error', 'Этап: ' .
                            implode(', ',$stagesNotDelete) . ' не может быть удален, т.к. участвует в играх этого турнира!');
                    }
                    $this->addError('use_stage');
                    $this->use_stages = $oldStages;
                    return false;
                }
            }
            return true;
        }
        return false;
    }
}
