<?php

namespace App\Http\Controllers;

use App\Helpers\BasicHelper;
use App\Helpers\TimeHelper;
use App\Helpers\UrlHelper;
use App\IgnoredTweet;
use App\Jobs\GlobalCommentTrackEvent;
use App\Jobs\GlobalLikeTrackEvent;
use App\Jobs\WritePostTrackEvent;
use App\Models\BookmarkedTweet;
use App\Models\LikedTweet;
use App\Models\TweetCommentReport;
use App\Models\TweetReport;
use App\Tweet;
use App\TweetComment;
use App\TweetGroup;
use App\User;
use App\UserMeta;
use App\UserTweetGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TweetsPageController extends Controller
{
	private $comment_short_len;
	private $tweet_short_len;
	private $tweets_per_page;

	public function __construct() {
		$this->comment_short_len = 100;
		$this->tweet_short_len = 300;
		$this->tweets_per_page = 10;
	}

    public function index(Request $request, TweetGroup $ch)
    {
    	$page = $request->input('page') && trim($request->input('page')) ? intval(trim($request->input('page'))) : 0;
	    if(!UserTweetGroup::where('group_id', $ch->id)->where(function($q){
		    $q->where('user_id', Auth::id())
		      ->orWhere('user_id', '-1');
	    })->count()){
		    return abort(403);
	    }
		$tweets = $ch->tweets();

	    $filter = $request->input('filter') ? trim($request->input('filter')) : null;
	    if($filter){
		    switch($filter){
			    case 'my':
				    $tweets = $tweets->where('user_id', Auth::id())->whereNotIn('id', function($q){
					    $q->select('tweet_id')->from('ignored_tweets');
				    });
				    break;
			    case 'bookmarked':
				    $tweets = $tweets->whereIn('id', function($q){
					    $q->select('tweet_id')->from('bookmarked_tweets')->where('user_id', Auth::id());
				    })->whereNotIn('id', function($q){
					    $q->select('tweet_id')->from('ignored_tweets');
				    });
				    break;
			    case 'hidden':
				    $tweets = $tweets->whereIn('id', function($q){
					    $q->select('tweet_id')->from('ignored_tweets')->where('user_id', Auth::id());
				    });
				    break;
			    default:
			    	$tweets = $tweets->whereNotIn('id', function($q){
					    $q->select('tweet_id')->from('ignored_tweets');
				    });
			    	break;
		    }
	    }else{
		    $tweets = $tweets->whereNotIn('id', function($q){
			    $q->select('tweet_id')->from('ignored_tweets');
		    });
	    }

	    $tweets = $tweets->take($this->tweets_per_page)->offset($page*$this->tweets_per_page)->orderBy('id', 'DESC')->get();

	    if(count($tweets) && Auth::id()){
		    Auth::user()->meta()->updateOrCreate([
			    'key' => 'last_channel_tweet_id'
		    ],[
			    'value' => $tweets[0]->id
		    ]);
	    }

	    return view('channels.channel', [
			'channel' => $ch,
			'tweets' => $tweets,
			'liked' => [],
			'filter' => $filter,
			'active' => [
				'top-menu' => 'community',
				'top-submenu' => 'channels'
			],
		]);
    }

	public function tweets(Request $request, TweetGroup $ch){
	    if(!UserTweetGroup::where('group_id', $ch->id)->where(function($q){
		    $q->where('user_id', Auth::id())
		      ->orWhere('user_id', '-1');
	    })->count()){
		    return abort(403);
	    }
	    $tweets = $ch->tweets()->selectExcept('text');
	    $lastID = intval($request->input('lastID'));
	    if($lastID){
	    	$tweets = $tweets->where('id', '<', $lastID);
	    }

	    $filter = $request->input('filter') ? trim($request->input('filter')) : null;
	    if($filter){
		    switch($filter){
			    case 'my':
				    $tweets = $tweets->where('user_id', Auth::id())->whereNotIn('id', function($q){
					    $q->select('tweet_id')->from('ignored_tweets');
				    });
				    break;
			    case 'bookmarked':
				    $tweets = $tweets->whereIn('id', function($q){
					    $q->select('tweet_id')->from('bookmarked_tweets')->where('user_id', Auth::id());
				    })->whereNotIn('id', function($q){
					    $q->select('tweet_id')->from('ignored_tweets');
				    });
				    break;
			    case 'hidden':
				    $tweets = $tweets->whereIn('id', function($q){
					    $q->select('tweet_id')->from('ignored_tweets')->where('user_id', Auth::id());
				    });
				    break;
			    default:
				    $tweets = $tweets->whereNotIn('id', function($q){
					    $q->select('tweet_id')->from('ignored_tweets');
				    });
				    break;
		    }
	    }else{
		    $tweets = $tweets->whereNotIn('id', function($q){
			    $q->select('tweet_id')->from('ignored_tweets');
		    });
	    }

	    $tweets = $tweets->with(['user' => function($q){
	    	$q->select('id', 'firstname', 'lastname', 'picture', 'last_activity');
	    }])->take($this->tweets_per_page)->orderBy('id', 'DESC')->get()->toJSON();

	    return response()->json([
	    	'tweets' => $tweets,
		    'channel' => [
		    	'id' => $ch->id
		    ]
	    ]);
    }
	
    public function tweet(Request $request, TweetGroup $ch, Tweet $tweet)
    {
	    if($ch->id != $tweet->group_id || !UserTweetGroup::where('group_id', $ch->id)->where(function($q){
		    $q->where('user_id', Auth::id())
		      ->orWhere('user_id', '-1');
	    })->count()){
		    return abort(403);
	    }

	    return view('channels.tweet', [
			'channel' => $ch,
			'tweet' => $tweet,
			'liked' => [],
		]);
    }

    public function editTweet(Request $request, TweetGroup $ch, Tweet $tweet)
    {
	    if($ch->id != $tweet->group_id || Auth::id() != $tweet->user_id){
		    return abort(403);
	    }

		return view('channels.tweet-edit', [
			'channel' => $ch,
			'tweet' => $tweet
		]);
    }

    public function updateTweet(Request $request, TweetGroup $ch, Tweet $tweet)
    {
	    if($ch->id != $tweet->group_id || Auth::id() != $tweet->user_id){
		    return response()->json(['message' => [trans('strings.forbidden-action').' '.trans('strings.item-does-not-belong-to-you')]], 403);
	    }

	    if($request->input('message')){
		    $rmessage = $request->message;
		    $rmessage = preg_replace('/<(p|div)[^<]*?>\s*<br\s*\/*\s*>\s*<\s*\/\s*(p|div)\s*>/i',"\r\n", $rmessage);
		    $rmessage = preg_replace('/<(p|div)[^<]*?>/i',"\r\n", $rmessage);
		    $rmessage = preg_replace('/<(br)[^<]*?>/i',"\r\n", $rmessage);
		    $rmessage = str_replace('&nbsp;',' ', $rmessage);
		    $rmessage = strip_tags($rmessage);
		    $rmessage = trim($rmessage, "\r\n");
		    $rmessage = trim($rmessage);
		    $request->merge(['message' => $rmessage]);
	    }
	    Validator::make($request->all(), [
		    'message' => 'required|min:1',
	    ])->validate();
	    $tweet_text = $request->message;
	    $short_tweet_text = $tweet_text;
	    $is_short = true;
	    if(mb_strlen($short_tweet_text, 'UTF-8') > $this->tweet_short_len){
		    $is_short = false;
		    $short_tweet_text = str_limit($tweet_text, $this->tweet_short_len, '');
	    }
	    $short_tweet_text_lines = explode("\r\n", $short_tweet_text);
	    if(count($short_tweet_text_lines) > 3){
		    $is_short = false;
		    array_splice($short_tweet_text_lines, 3);
		    $short_tweet_text = implode("\r\n", $short_tweet_text_lines);
	    }
	    if($is_short){
		    $tweet_text = '';
	    }
	    $updated = $tweet->update([
		    'text' => $tweet_text,
		    'short_text' => $short_tweet_text,
		    'is_short' => $is_short,
	    ]);
	    if($updated){
		    setcookie('pushState', '/ch/'.$tweet->group_id.'/tweet/'.$tweet->id, time()+(60), '/');
		    return redirect('/ch/'.$tweet->group_id.'/tweet/'.$tweet->id);

	    }
	    return response()->json([], 500);
    }

    public function comments(Request $request, Tweet $tweet, TweetComment $comment){
		$lastID = null;
		$start_offset = 2;
		$per_page = 10;
		$display_children = false;
		$parent_comment_id = null;
		if($request->input('lastID') && trim($request->input('lastID'))){
			$lastID = intval($request->input('lastID'));
		}
		if($tweet){
			if(!UserTweetGroup::where('group_id', $tweet->group_id)->where(function($q){
				$q->where('user_id', Auth::id())
				  ->orWhere('user_id', '-1');
			})->count()){
				return abort(403);
			}
		}
	    $comments = $tweet->comments()->where('status', 1);
		if(isset($comment) && $comment->id) {
			$display_children = true;
			$parent_comment_id = $comment->id;
			$comments = $comments->where(function($q) use ($comment){$q->where('belong_to', $comment->id)->orWhere('reply_to', $comment->id);});
		}else{
			$comments = $comments->where(function($q){$q->whereNull('belong_to')->orWhere('belong_to', 0)->orWhere('belong_to', '');});
		}
		$count_all = clone $comments;
		$count_all = $count_all->count();
		if($lastID){
			$comments = $comments->where('id', '<', $lastID);
		}
	    $comments = $comments->take(($lastID ? $per_page : $start_offset))->orderBy('id', 'desc')->get();
		$show_previous_link = false;
		if($lastID){
			if(count($comments) == $per_page){
				$show_previous_link = true;
			}
		}else{
			if(count($comments) == $start_offset){
				$show_previous_link = true;
			}
		}
		return view('tweet.comments', [
			'tweet' => $tweet,
			'comments' => $comments,
			'count' => $count_all,
			'display_children' => $display_children,
			'parent_comment_id' => $parent_comment_id,
			'show_previous_link' => $show_previous_link,
			'first_page' => $lastID ? false : true
		]);
    }

    public function addComment(Request $request, Tweet $tweet){
	    Validator::extend('reply_check', function ($attribute, $value, $parameters, $validator) use ($tweet) {
		    if(TweetComment::where(['id' => $value, 'tweet_id' => $tweet->id, 'status' => 1])->count()){
			    return true;
		    }
		    return false;
	    });
	    if($request->input('comment')){
	    	$rcomment = preg_replace('/<(p|div)[^<]*?>\s*<br\s*\/*\s*>\s*<\s*\/\s*(p|div)\s*>/i',"\r\n", $request->comment);
		    $rcomment = preg_replace('/<(p|div)[^<]*?>/i',"\r\n", $rcomment);
		    $rcomment = preg_replace('/<(br)[^<]*?>/i',"\r\n", $rcomment);
		    $rcomment = str_replace('&nbsp;',' ', $rcomment);
		    $rcomment = strip_tags($rcomment);
		    $rcomment = trim($rcomment, "\r\n");
		    $rcomment = trim($rcomment);
		    $request->merge(['comment' => $rcomment]);
	    }
	    Validator::make($request->all(), [
		    'comment' => 'required|min:1',
		    'reply_to' => 'integer|reply_check',
	    ])->validate();

	    if($tweet->status == '1'){
		    if(!UserTweetGroup::where('group_id', $tweet->group_id)->where(function($q){
			    $q->where('user_id', Auth::id())
			      ->orWhere('user_id', '-1');
		    })->count()){
			    return abort(403);
		    }
		    $reply_to = null;
		    $belong_to = null;
		    if($request->input('reply_to') && trim($request->input('reply_to'))){
			    $reply_to = intval($request->input('reply_to'));
			    $reply_comment = TweetComment::where(['id' => intval($request->input('reply_to'))])->first();
			    if($reply_comment){
				    if(trim($reply_comment->belong_to)){
					    $belong_to = $reply_comment->belong_to;
				    }else{
					    $belong_to = $reply_comment->id;
				    }
			    }
		    }
		    $comment_text = $request->comment;
		    $short_comment_text = $comment_text;
		    $is_short = true;
		    if(mb_strlen($short_comment_text, 'UTF-8') > $this->comment_short_len){
		    	$is_short = false;
			    $short_comment_text = str_limit($comment_text, $this->comment_short_len, '');
		    }
		    $short_comment_text_lines = explode("\r\n", $short_comment_text);
		    if(count($short_comment_text_lines) > 3){
		    	$is_short = false;
		    	array_splice($short_comment_text_lines, 3);
			    $short_comment_text = implode("\r\n", $short_comment_text_lines);
		    }
		    if($is_short){
		    	$comment_text = '';
		    }
		    $comment = $tweet->comments()->create([
			    'user_id' => Auth::id(),
			    'comment' => $comment_text,
			    'short_comment' => $short_comment_text,
			    'is_short' => $is_short,
			    'reply_to' => $reply_to,
			    'belong_to' => $belong_to,
			    'status' => 1
		    ]);
		    if($comment){
			    $tweet->update(['comments' => intval($tweet->comments)+1]);
			    if($belong_to){
			    	$parent_tweet_comment = TweetComment::find($belong_to);
			    	$parent_replies_counter = 0;
			    	if($parent_tweet_comment){
					    $parent_replies_counter = intval($parent_tweet_comment->child_count) + 1;
					    $parent_tweet_comment->child_count = $parent_replies_counter;
					    $parent_tweet_comment->save();
				    }
				    $comment->date = '<time class="need-fix-time" data-timetype="date" data-time="'.$comment->created_at->timestamp.'">'.TimeHelper::localizedDate(strtotime($comment->created_at)).'</time>';
				    $comment->time = '<time data-time="'.$comment->created_at->timestamp.'" data-timetype="H:i" class="need-fix-date">'.date('H:i', strtotime($comment->created_at)).'</time>';
				    $user = User::select('id', 'firstname', 'lastname', 'picture', 'last_activity')->where('id', $comment->user_id)->first();
				    if($user){
					    $user->profile_url = $user->getProfileUrl();
					    $user->picture_url = $user->getPictureUrl();
					    $user->name_letter = mb_substr($user->firstname, 0, 1, 'UTF-8');
					    $user->online = (strtotime('now') - strtotime($user->last_activity)) < 300 ? true : false;
				    }
				    $comment->replies_counter_link = '<a href="#" class="replies-to-comment view-tweet-comment-replies" data-tweet-id="'.$tweet->id.'" data-cid="'.$comment->belong_to.'"><i class="fa fa-comments" aria-hidden="true"></i> '.$parent_replies_counter.' '. trans_choice('strings.answers', $parent_replies_counter, [], '', app()->getLocale()) .'</a>';
				    $comment->user = $user;
				    unset($comment->comment);
				    $data = [
					    'secret' => env('WS_KEY'),
					    'data' => json_encode([
						    'ping_type' => 'tweet-comment-reply-created',
						    'ping_data' => [
							    'channel' => [
								    'id' => $tweet->group_id
							    ],
							    'tweet' => [
								    'id' => $tweet->id
							    ],
							    'comment' => $comment,
							    'privacy' => []
						    ]
					    ])
				    ];
				    $curl = curl_init("http://127.0.0.1:8002/req");
				    curl_setopt($curl, CURLOPT_POST, 1);
				    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
				    curl_setopt($curl, CURLOPT_POSTFIELDS , $data);
				    curl_exec($curl);
				    curl_close($curl);

				    $reply_to_comment = null;
				    if($reply_to){
				    	$reply_to_comment = TweetComment::find($reply_to);
				    }
				    $this->trackReplyCommentEvent($tweet, $comment, $reply_to_comment);
			    }else{
			    	$comment->child_count = intval($comment->child_count);
			    	$comment->show_replies_link = $comment->child_count ? true : false;
			    	$comment->child_count_text = trans_choice('strings.answers', $comment->child_count, [], '', app()->getLocale());
				    $comment->date = '<time class="need-fix-time" data-timetype="date" data-time="'.$comment->created_at->timestamp.'">'.TimeHelper::localizedDate(strtotime($comment->created_at)).'</time>';
				    $comment->time = '<time data-time="'.$comment->created_at->timestamp.'" data-timetype="H:i" class="need-fix-date">'.date('H:i', strtotime($comment->created_at)).'</time>';
				    $user = User::select('id', 'firstname', 'lastname', 'picture', 'last_activity')->where('id', $comment->user_id)->first();
				    if($user){
					    $user->profile_url = $user->getProfileUrl();
					    $user->picture_url = $user->getPictureUrl();
					    $user->name_letter = mb_substr($user->firstname, 0, 1, 'UTF-8');
					    $user->online = (strtotime('now') - strtotime($user->last_activity)) < 300 ? true : false;
				    }
				    $comment->user = $user;
				    unset($comment->comment);
				    $data = [
					    'secret' => env('WS_KEY'),
					    'data' => json_encode([
						    'ping_type' => 'tweet-comment-created',
						    'ping_data' => [
							    'channel' => [
							    	'id' => $tweet->group_id
							    ],
							    'tweet' => [
							    	'id' => $tweet->id
							    ],
							    'comment' => $comment,
							    'privacy' => []
						    ]
					    ])
				    ];
				    $curl = curl_init("http://127.0.0.1:8002/req");
				    curl_setopt($curl, CURLOPT_POST, 1);
				    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
				    curl_setopt($curl, CURLOPT_POSTFIELDS , $data);
				    curl_exec($curl);
				    curl_close($curl);

				    $this->trackCommentEvent($tweet, $comment);
			    }
		    }
	    }else{
		    return abort(403);
	    }
    }

    public function deleteComment(TweetComment $comment){
    	if($comment->user_id == Auth::id()){
    		$data = [];
    		$data['comment']['id'] = $comment->id;
    		$data['tweet']['id'] = $comment->tweet_id;
    		$children_removed = $comment->children()->where('status', 1)->count();
    		if(trim($comment->belong_to)){
			    $data['comment']['belongs_to'] = intval($comment->belong_to);
				$parent = $comment->parent()->first();
			    $parent_children_count = 0;
				if($parent) {
					$parent->child_count = ( $parent->child_count - 1 > 0 ) ? $parent->child_count - 1 : 0;
					$parent->save();
					$parent_children_count = $parent->child_count;
				}
			    $data['comment']['parent']['child_count'] = $parent_children_count;
			    $data['comment']['parent']['child_count_text'] = trans_choice('strings.answers', $parent_children_count, [], '', app()->getLocale());
		    }
		    $tweet = $comment->tweet()->first();
    		if($tweet) {
    			$removed = 1;
			    if ($children_removed) {
				    $removed += intval($children_removed);
			    }
			    $tweet->comments = ($tweet->comments - $removed > 0) ? $tweet->comments - $removed : 0;
				$tweet->save();
			    $data['tweet']['comments'] = $tweet->comments;
			    $data['tweet']['removed_count'] = $removed;
		    }
    		$comment->delete();
    		return response()->json($data);
	    }else{
		    return abort(403);
	    }
    }

    public function reportComment(Request $request, TweetComment $comment){
    	if($comment->user_id == Auth::id()){
		    return abort(403);
	    }
        $tweet = $comment->tweet()->first();
        if(!$tweet){
		    return abort(403);
	    }
	    if(!UserTweetGroup::where('group_id', $tweet->group_id)->where(function($q){
		    $q->where('user_id', Auth::id())
		      ->orWhere('user_id', '-1');
	    })->count()){
		    return abort(403);
	    }
	    $data = [];
	    if($request->input('undo')){
			$report = TweetCommentReport::where([
				'reporter_id' => Auth::id(),
				'comment_id' => $comment->id,
				'tweet_id' => $tweet->id
			])->delete();
			$data['undo'] = true;
	    }else{
		    $data['undo'] = false;
		    $report = TweetCommentReport::updateOrCreate([
			    'reporter_id' => Auth::id(),
			    'comment_id' => $comment->id,
			    'tweet_id' => $tweet->id
		    ],
			    [
				    'reporter_id' => Auth::id(),
				    'comment_id' => $comment->id,
				    'tweet_id' => $tweet->id
			    ]);
	    }

        if($report) {
	        $data['comment']['id'] = $comment->id;
	        $data['tweet']['id']   = $comment->tweet_id;
        }
        return response()->json($data);
    }

    public function commentExpanded(TweetComment $comment) {
	    if ( $comment ) {
		    $tweet = $comment->tweet()->firstOrFail();
		    if ( ! UserTweetGroup::where( 'group_id', $tweet->group_id )->where( function ( $q ) {
			    $q->where( 'user_id', Auth::id() )
			      ->orWhere( 'user_id', '-1' );
		    } )->count()
		    ) {
			    return abort( 403 );
		    }
		    if ( $comment->is_short ) {
			    echo BasicHelper::text2html($comment->short_comment);
		    } else {
			    echo BasicHelper::text2html($comment->comment);
		    }
	    }
    }

    public function tweetExpanded(Tweet $tweet) {
	    if ( $tweet ) {
		    if (! UserTweetGroup::where( 'group_id', $tweet->group_id )->where( function ( $q ) {
			    $q->where( 'user_id', Auth::id() )
			      ->orWhere( 'user_id', '-1' );
		    } )->count()
		    ) {
			    return abort( 403 );
		    }
		    if ( $tweet->is_short ) {
			    echo BasicHelper::text2html($tweet->short_text);
		    } else {
			    echo BasicHelper::text2html($tweet->text);
		    }
	    }
    }

    public function hideTweet(Request $request, TweetGroup $ch, Tweet $tweet){
	    if($tweet->user_id == Auth::id()){
		    return response()->json([
			    'message' => trans('strings.senseless-action').' '.trans('strings.hide-own-post')
		    ], 403);
	    }
	    if(!UserTweetGroup::where('group_id', $tweet->group_id)->where(function($q){
		    $q->where('user_id', Auth::id())
		      ->orWhere('user_id', '-1');
	    })->count()){
		    return response()->json([
			    'message' => trans('strings.forbidden-action')
		    ], 403);
	    }
	    $data = [];
	    if($request->input('undo')){
		    $ignore = IgnoredTweet::where([
			    'user_id' => Auth::id(),
			    'tweet_id' => $tweet->id
		    ])->delete();
		    $data['undo'] = true;
	    }else{
		    $data['undo'] = false;
		    $ignore = IgnoredTweet::updateOrCreate([
			    'user_id' => Auth::id(),
			    'tweet_id' => $tweet->id
		    ],
		    [
			    'user_id' => Auth::id(),
			    'tweet_id' => $tweet->id
		    ]);
	    }

	    if($ignore) {
		    $data['tweet']['id'] = $tweet->id;
		    return response()->json($data);
	    }else{
		    return response()->json([], 500);
	    }
    }

	public function bookmarkTweet(Request $request, TweetGroup $ch, Tweet $tweet){
	    if($tweet->group_id != $ch->id){
		    return response()->json([
			    'message' => trans('strings.forbidden-action')
		    ], 403);
	    }
	    if(!UserTweetGroup::where('group_id', $tweet->group_id)->where(function($q){
		    $q->where('user_id', Auth::id())
		      ->orWhere('user_id', '-1');
	    })->count()){
		    return response()->json([
			    'message' => trans('strings.forbidden-action')
		    ], 403);
	    }
	    $data = [];
	    if($request->input('undo')){
		    $bookmark = BookmarkedTweet::where([
			    'user_id' => Auth::id(),
			    'tweet_id' => $tweet->id
		    ])->delete();
		    $data['undo'] = true;
	    }else{
		    $data['undo'] = false;
		    $bookmark = BookmarkedTweet::updateOrCreate([
			    'user_id' => Auth::id(),
			    'tweet_id' => $tweet->id
		    ],
		    [
			    'user_id' => Auth::id(),
			    'tweet_id' => $tweet->id
		    ]);
	    }

	    if($bookmark) {
		    $data['tweet']['id'] = $tweet->id;
		    return response()->json($data);
	    }else{
		    return response()->json([], 500);
	    }
    }

	public function likeTweet(Request $request, TweetGroup $ch, Tweet $tweet){
	    if($tweet->group_id != $ch->id){
		    return response()->json([
			    'message' => trans('strings.forbidden-action')
		    ], 403);
	    }
	    if(!UserTweetGroup::where('group_id', $tweet->group_id)->where(function($q){
		    $q->where('user_id', Auth::id())
		      ->orWhere('user_id', '-1');
	    })->count()){
		    return response()->json([
			    'message' => trans('strings.forbidden-action')
		    ], 403);
	    }
	    $data = [];
	    if($request->input('undo') && intval($request->input('undo'))){
		    $like = LikedTweet::where([
			    'user_id' => Auth::id(),
			    'tweet_id' => $tweet->id
		    ])->delete();
		    $tweet->likes = ($tweet->likes - 1) >= 0 ? $tweet->likes - 1 : 0;
		    $tweet->save();
		    $data['undo'] = true;
	    }else{
		    $data['undo'] = false;
		    $like = LikedTweet::create([
			    'user_id' => Auth::id(),
			    'tweet_id' => $tweet->id
		    ]);
		    if($like) {
			    $tweet->likes = ( $tweet->likes + 1 ) >= 0 ? $tweet->likes + 1 : 0;
			    $tweet->save();
			    $this->trackLikeEvent($tweet);
		    }
	    }

	    if($like) {
		    $cdata = [
			    'secret' => env('WS_KEY'),
			    'data' => json_encode([
				    'ping_type' => 'tweet-liked',
				    'ping_data' => [
					    'channel_id' => $ch->id,
					    'tweet' => [
					    	'id' => $tweet->id,
						    'likes' => $tweet->likes
					    ],
					    'user_id' => Auth::id(),
					    'undo' => $data['undo'],
					    'privacy' => []
				    ]
			    ])
		    ];
		    $curl = curl_init("http://127.0.0.1:8002/req");
		    curl_setopt($curl, CURLOPT_POST, 1);
		    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		    curl_setopt($curl, CURLOPT_POSTFIELDS , $cdata);
		    curl_exec($curl);
		    curl_close($curl);

		    $data['tweet']['id'] = $tweet->id;
		    return response()->json($data);
	    }else{
		    return response()->json([], 500);
	    }
    }

    public function reportTweet(Request $request, TweetGroup $ch, Tweet $tweet){
	    if($tweet->user_id == Auth::id()){
		    return response()->json([
			    'message' => trans('strings.senseless-action').' '.trans('strings.complain-about-own-post')
		    ], 403);
	    }
	    if(!UserTweetGroup::where('group_id', $tweet->group_id)->where(function($q){
		    $q->where('user_id', Auth::id())
		      ->orWhere('user_id', '-1');
	    })->count()){
		    return abort(403);
	    }
	    $data = [];
	    if($request->input('undo')){
		    $report = TweetReport::where([
			    'user_id' => Auth::id(),
			    'tweet_id' => $tweet->id
		    ])->delete();
		    $data['undo'] = true;
	    }else{
		    $data['undo'] = false;
		    $report = TweetReport::updateOrCreate([
			    'user_id' => Auth::id(),
			    'tweet_id' => $tweet->id
		    ],
		    [
			    'user_id' => Auth::id(),
			    'tweet_id' => $tweet->id
		    ]);
	    }

	    if($report) {
		    $data['tweet']['id'] = $tweet->id;
		    return response()->json($data);
	    }else{
	    	return response()->json([], 500);
	    }
    }

    public function lastViewed(Request $request, TweetGroup $ch, Tweet $tweet){
    	if(!Auth::id() || $ch->id != $tweet->group_id){
    		return 403;
	    }
	    if(!UserTweetGroup::where('group_id', $tweet->group_id)->where(function($q){
		    $q->where('user_id', Auth::id())
		      ->orWhere('user_id', '-1');
	    })->count()){
		    return abort(403);
	    }
	    Auth::user()->meta()->updateOrCreate([
		    'key' => 'last_channel_tweet_id'
	    ],[
		    'value' => $tweet->id
	    ]);
    }

    public function deleteTweet(TweetGroup $ch, Tweet $tweet){
    	if($tweet->user_id == Auth::id()){
    		$tweet->delete();
		    return response()->json([
			    'tweet' => [
				    'id' => $tweet->id
			    ]
		    ]);
	    }else{
		    return response()->json([
			    'message' => trans('strings.forbidden-action').' '.trans('strings.item-does-not-belong-to-you')
		    ], 403);
	    }
    }

	private function trackLikeEvent(Tweet $tweet)
	{
		/**
		 * u - user id
		 * p - post id
		 * f - post format (public | board)
		 * t - type (p1 - new post, p2 - update, l1 - new like)
		 * ts - time
		 */
		$like_data = [
			'u'=> Auth::id(),
			'p' => $tweet->id,
			't' => 't1',
			'ts' => time(),
		];
		dispatch(new GlobalLikeTrackEvent($like_data));
		if(!Auth::id() || !$tweet->user_id || Auth::id() == $tweet->user_id){
			return false;
		}
		$log_data = [];
		if(file_exists(storage_path('user_posts_activities/'.$tweet->user_id.'.log'))){
			$log_data = json_decode(file_get_contents(storage_path('user_posts_activities/'.$tweet->user_id.'.log')), true) ? : [];
		}
		$today = date('Y-m-d');
		if(!isset($log_data[$today])){
			$add = [
				$today => [
					[
						'id'=> Auth::id(),
						'tweet_id' => $tweet->id,
						'channel_id' => $tweet->group_id,
						'type' => 'tweet_like',
						'timestamp' => time(),
						'message' => ''
					]
				]
			];
			$log_data = $add + $log_data;
			if(count($log_data) > 10){
				array_pop($log_data);
			}
			dispatch(new WritePostTrackEvent($tweet->user_id, $log_data));
		}else{
			if($lpd = count($log_data[$today])){
				$liked = false;
				for($i = 0; $i < $lpd; $i++){
					if($log_data[$today][$i]['id'] == Auth::id() && $log_data[$today][$i]['tweet_id'] == $tweet->id && $log_data[$today][$i]['type'] == 'tweet_like'){
						$liked = true;
						break;
					}
				}
				if(!$liked){
					$add = [
						'id'=> Auth::id(),
						'tweet_id' => $tweet->id,
						'channel_id' => $tweet->group_id,
						'type' => 'tweet_like',
						'timestamp' => time(),
						'message' => ''
					];
					array_unshift($log_data[$today], $add);
					dispatch(new WritePostTrackEvent($tweet->user_id, $log_data));
				}
			}
		}
	}

	private function trackCommentEvent(Tweet $tweet, $comment)
	{
		/**
		 * u - user id
		 * p - post id
		 * f - post format (public | board)
		 * c - comment id
		 * t - type (p1 - new post, p2 - update, l1 - new like)
		 * ts - time
		 */
		$comment_data = [
			'u'=> Auth::id(),
			'p' => $tweet->id,
			'c' => $comment->id,
			't' => 'tc1',
			'ts' => time(),
		];
		dispatch(new GlobalCommentTrackEvent($comment_data));
		if(!Auth::id() || !$tweet->user_id || Auth::id() == $tweet->user_id){
			return false;
		}
		$log_data = [];
		if(file_exists(storage_path('user_posts_activities/'.$tweet->user_id.'.log'))){
			$log_data = json_decode(file_get_contents(storage_path('user_posts_activities/'.$tweet->user_id.'.log')), true) ? : [];
		}
		$today = date('Y-m-d');
		if(!isset($log_data[$today])){
			$add = [
				$today => [
					[
						'id'=> Auth::id(),
						'tweet_id' => $tweet->id,
						'channel_id' => $tweet->group_id,
						'comment_id' => $comment->id,
						'comment_text' => str_limit($comment->short_comment, 200),
						'type' => 'tweet_comment',
						'timestamp' => time(),
						'message' => ''
					]
				]
			];
			$log_data = $add + $log_data;
			if(count($log_data) > 10){
				array_pop($log_data);
			}
			dispatch(new WritePostTrackEvent($tweet->user_id, $log_data));
		}else{
			if($cpd = count($log_data[$today])){
				$commented = false;
				if(!$commented){
					$add = [
						'id'=> Auth::id(),
						'tweet_id' => $tweet->id,
						'channel_id' => $tweet->group_id,
						'comment_id' => $comment->id,
						'comment_text' => str_limit($comment->short_comment, 200),
						'type' => 'tweet_comment',
						'timestamp' => time(),
						'message' => ''
					];
					array_unshift($log_data[$today], $add);
					dispatch(new WritePostTrackEvent($tweet->user_id, $log_data));
				}
			}
		}
	}

	private function trackReplyCommentEvent(Tweet $tweet, $reply, $comment)
	{
		/**
		 * u - user id
		 * p - post id
		 * f - post format (public | board)
		 * c - comment id
		 * t - type (p1 - new post, p2 - update, l1 - new like)
		 * ts - time
		 */
		if(!Auth::id() || !$tweet->user_id || !$comment){
			return false;
		}
		if(Auth::id() != $comment->user_id) {
			$log_data = [];
			if ( file_exists( storage_path( 'user_posts_activities/' . $comment->user_id . '.log' ) ) ) {
				$log_data = json_decode( file_get_contents( storage_path( 'user_posts_activities/' . $comment->user_id . '.log' ) ), true ) ?: [];
			}
			$today = date( 'Y-m-d' );
			if ( ! isset( $log_data[ $today ] ) ) {
				$add      = [
					$today => [
						[
							'id'               => Auth::id(),
							'tweet_id'         => $tweet->id,
							'channel_id'       => $tweet->group_id,
							'reply_comment_id' => $comment->id,
							'comment_id'       => $reply->id,
							'comment_text'     => str_limit( $reply->short_comment, 200 ),
							'type'             => 'reply_tweet_comment',
							'timestamp'        => time(),
							'message'          => ''
						]
					]
				];
				$log_data = $add + $log_data;
				if ( count( $log_data ) > 10 ) {
					array_pop( $log_data );
				}
				dispatch( new WritePostTrackEvent( $comment->user_id, $log_data ) );
			} else {
				$add = [
					'id'               => Auth::id(),
					'tweet_id'         => $tweet->id,
					'channel_id'       => $tweet->group_id,
					'reply_comment_id' => $comment->id,
					'comment_id'       => $reply->id,
					'comment_text'     => str_limit( $reply->short_comment, 200 ),
					'type'             => 'reply_tweet_comment',
					'timestamp'        => time(),
					'message'          => ''
				];
				array_unshift( $log_data[ $today ], $add );
				dispatch( new WritePostTrackEvent( $comment->user_id, $log_data ) );
			}
		}
		if($tweet->user_id != $comment->user_id && Auth::id() != $tweet->user_id) {
			$this->trackCommentEvent( $tweet, $reply );
		}
	}
}
