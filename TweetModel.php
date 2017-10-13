<?php

namespace App;

use App\Helpers\TimeHelper;
use App\Models\BookmarkedTweet;
use App\Models\LikedTweet;
use App\Models\TweetCommentReport;
use App\Models\TweetReport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Tweet extends Model
{

	protected $fillable = [
		'group_id',
		'user_id',
		'text',
		'short_text',
		'is_short',
		'json',
		'likes',
		'comments',
		'views'
	];

	protected $columns = [
		'id',
		'group_id',
		'user_id',
		'text',
		'short_text',
		'is_short',
		'json',
		'likes',
		'comments',
		'views',
		'updated_at',
		'created_at',
	];

	protected $appends = ['human_time', 'reported', 'bookmarked', 'ignored', 'liked'];

	public function user()
	{
		return $this->belongsTo(User::class, 'user_id');
	}
	public function group()
	{
		return $this->belongsTo(TweetGroup::class, 'group_id');
	}
	public function ignored()
	{
		return $this->hasMany(IgnoredTweet::class, 'tweet_id');
	}
	public function comments()
	{
		return $this->hasMany(TweetComment::class);
	}
	public function reported_comments()
	{
		return $this->hasMany(TweetCommentReport::class, 'tweet_id');
	}
	public function reports()
	{
		return $this->hasMany(TweetReport::class, 'tweet_id');
	}

	public function bookmarks()
	{
		return $this->hasMany(BookmarkedTweet::class, 'tweet_id');
	}

	public function likes()
	{
		return $this->hasMany(LikedTweet::class, 'tweet_id');
	}

	public function getHumanDate($type = 0)
	{
		if($type == 0) {
			if (strtotime($this->created_at) >= strtotime('today')) {
				return '<time class="need-fix-date" data-timetype="H:i" data-time="'.$this->created_at->timestamp.'">'.date('H:i', strtotime($this->created_at)).'</time>';
			} else {
				if (strtotime($this->created_at) > 0) {
					return '<time class="need-fix-date" data-timetype="date" data-time="'.$this->created_at->timestamp.'">'.TimeHelper::localizedDate(strtotime($this->created_at)).'</time>';
				}
			}
		}
	}

	public function getHumanTimeAttribute(){
		return $this->getHumanDate();
	}

	public function getReportedAttribute(){
		if(Auth::id()){
			return $this->reports()->where('user_id', Auth::id())->count() ? 1 : 0;
		}else{
			return 0;
		}
	}

	public function getIgnoredAttribute(){
		if(Auth::id()){
			return $this->ignored()->where('user_id', Auth::id())->count() ? 1 : 0;
		}else{
			return 0;
		}
	}

	public function getBookmarkedAttribute(){
		if(Auth::id()){
			return $this->bookmarks()->where('user_id', Auth::id())->count() ? 1 : 0;
		}else{
			return 0;
		}
	}

	public function getLikedAttribute(){
		if(Auth::id()){
			return $this->likes()->where('user_id', Auth::id())->count() ? 1 : 0;
		}else{
			return 0;
		}
	}

	public function scopeSelectExcept($query, $value = []){
		return $query->select(array_diff($this->columns, (array) $value));
	}
}
