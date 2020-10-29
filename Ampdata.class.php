<?php
class Ampdata{

	public function __construct(){
		include('/usr/share/nginx/lokmat-web/app/amp/includes/include.php');
		include('/usr/share/nginx/lokmat-web/app/amp/config/config.php');
	}

	public function ampdataFun(){
		echo "\nI am called from AMPdata class autoload\n";
		incFun();
	}



	public function getFeaturedIdsData($s3Path, $dbids, $query, $client){
		global $domainName, $featuredFields;

		if(!empty($dbids)){
			foreach ($dbids['featured_dbid'] as $featuredIds) {
				$featuredIdsQuery[] = 'dbid:"' . $featuredIds . '"';
			}
		}else{
			$fileData = file_get_contents($s3Path);
			if(!empty($fileData)){
				$dbidsCollection = explode(',',$fileData);
				foreach($dbidsCollection as $k=>$featuredIds){
					$featuredIdsQuery[] = 'dbid:"' . $featuredIds . '"';
				}

			}else{
				return array();
			}
		}

		$sQuery = 'contenttype:' . SOLR_GROUPED_CONTENTTYPE . ' AND source:"lokmat" AND status:"published" AND -genuine_gallery:false AND -redirecturl:*';
		$aSorts = array('moddate' => 'desc');
		$sEndpoint = "lokmat";
		$aFields = $featuredFields;
		$sGroupField = "";
		$arrGroupQuery = $featuredIdsQuery;
		$arrSpecialCondition = array("pubdate" => "ago_format", "moddate" => "ago_format");
		$iArticleLimit = 20;

		$solrData = array();
		$solrData = $query->getGroupViaQuery($client, $sEndpoint, $sQuery, $aFields, $sGroupField, $aSorts, $iGroupLimit = 10, $iArticleLimit, $iPage = 1, "", $arrSpecialCondition, $arrGroupQuery);


		return $solrData;
	}

	public function getHomeFeaturedList($splotData, $query, $client){

		global $domainName, $featuredFields;

		$homeFeaturedData = $mainCarousalHtml = $fThreeHtml = $fPhotoHtml = $videoHtml = $sThreeHtml = $sPhotoHtml = $remainingHtml = "";
		$featuredArticles = $this->getFeaturedIdsData("", $splotData, $query, $client);
		$featuredPhotos = $this->getFeaturedIdsData("https://s3.ap-south-1.amazonaws.com/lokmat.com/featured_photo_galleries.txt", array(), $query, $client);
		$featuredVideos = $this->getFeaturedIdsData("https://s3.ap-south-1.amazonaws.com/lokmat.com/lokmat/featured_videos.txt", array(), $query, $client);

		if(!empty($featuredArticles)){

			$mainCarousal = array_slice($featuredArticles, 0, 5, true);
			$mainCarousalHtml = $this->getCarousal($mainCarousal);

			$fThree = array_slice($featuredArticles, 5, 3, true);
			$fThreeHtml = $this->getListView($fThree);

			$sThree = array_slice($featuredArticles, 8, 3, true);
			$sThreeHtml = $this->getListView($sThree);

			$tThree = array_slice($featuredArticles, 11, 3, true);
                        $tThreeHtml = $this->getListView($tThree);

			$remaining = array_slice($featuredArticles, 14, 20, true);
			$remainingHtml = $this->getListView($remaining);

			if(!empty($featuredPhotos)){
				$fphotosData = array_slice($featuredPhotos, 0, 1, true);
				$fPhotoHtml = $this->getListView($fphotosData);

				$sphotosData = array_slice($featuredPhotos, 1, 1, true);
				$sPhotoHtml = $this->getPhotoStory($sphotosData);

				$tphotosData = array_slice($featuredPhotos, 2, 1, true);
                                $tPhotoHtml = $this->getPhotoStory($tphotosData);

			}

			if(!empty($featuredVideos)){
				$videosData = array_slice($featuredVideos, 0, 3, true);
				$videoHtml = $this->getGridView($videosData);
			}

			$homeFeaturedData = $mainCarousalHtml.$fThreeHtml.$fPhotoHtml.$videoHtml.$sThreeHtml.$sPhotoHtml.$tThreeHtml.$tPhotoHtml.$remainingHtml;
		}

		return '<section id="featured_widget">'.$homeFeaturedData.'</section>';
	}

	public function getHomeCategoryList($splotData, $query, $client){
		global $domainName;
		$content = "";
		//($splotData['categoryslug']);
		$apiData = file_get_contents('http://api.lokmat.com/mr/home');
		$apiDataDecode = json_decode($apiData, 'true');
		foreach($apiDataDecode['data'] as $k=>$v){
			if($splotData['categoryslug'] == $v['widgetId']){
				$content_.$v['widgetId'] = '<section class="lkm-widget home-widget">
					  <div class="lkm-head">
					  <h3 class="widget-head">'.$v['title'].'</h3>
					  <a class="read-all" href="'.$domainName.'/'.$v['widgetId'].'/">Read more &raquo;</a>
					</div>';
				//print_r($v['data']);die('********');
	
				$gridView = array_slice($v['data'],0,5,true);
				$gridViewApi = $this->solrToApi($gridView);
				$gridHtml = $this->getGridView($gridViewApi);
				$content_.$v['widgetId'] .= $gridHtml;
	
				//print_r($v['data']);die();
			
				$listView = array_slice($v['data'],5,2,true);
				//print_r($listView);die("-----------");
				$listViewApi = $this->solrToApi($listView);
				//print_r($listViewApi);
				//die("+++++++++++");
				$listHtml = $this->getListView($listViewApi);
				$content_.$v['widgetId'] .= $listHtml;

				$content_.$v['widgetId'] .= '</section>';
				$content .= $content_.$v['widgetId'];
			}
		}

		return $content;
	}

	public function getHomePage($query, $client){
		global $domainName;

		$contentAmp = $homeFeaturedData = $homeCategoryData = "";

		/**
		 * Trending Topics
		 */
		$trendingTopics = file_get_contents('https://s3.ap-south-1.amazonaws.com/lokmat.com/lokmat/amp_trending_data.html');
		$contentAmp .= $trendingTopics;

		/**
		 * Get the splots
		 */
		$sQuery = 'pagename:"homepage-mobile-v2" AND -htmlcontent:* AND status:active';
		$aSorts = array('order' => 'asc');
		$sEndpoint = "splots";
		$aFields = array("*");
		$iArticleLimit = 100;
		$iPage = 1;

		$splotsData = array();
		$splotsData = $query->getSimpleSelect($client, $sEndpoint, $sQuery, $aFields, $aSorts, $iArticleLimit, $iPage);


		if(isset($splotsData[0])){
			foreach($splotsData[0] as $k => $v ){
				if($v['featured_dbid']){
					$homeFeaturedData = $this->getHomeFeaturedList($v, $query, $client);					

				}elseif($v['categoryslug']){
					$catData = $this->getHomeCategoryList($v, $query, $client);
					$homeCategoryData .= $catData;
				}
			}
		}

		$contentAmp .= $homeFeaturedData.$homeCategoryData;
		return $contentAmp;
	}	


	public function getPhotoStory($data){

		global $domainName;

		foreach($data as $k=>$v){
			if(isset($v[0])){
				$v = $v[0];
			}
		}

		$content = '<figure class="photo-story">
			<h2><a class="list-category" href="'.$domainName.'/'.$v['categorybadgeslug'].'/">'.$v['categorybadge_regional'].' :</a> <a href="'.getPermalink($v).'">'.$v['title_regional'].'</a> </h2>
			<div class="photos-list">
			<a href="'.getPermalink($v).'">';

		if(isset($v['gallery_url'])){
			foreach($v['gallery_url'] as $k=>$image){
				
				$imageServerPathCon = getTheImageServerPath($image);
				$featuredImage = imageResizer($image, $imageServerPathCon, '300x225');

				$content .= '<span class="imgwrap photo_story">
					<amp-img src="'.$featuredImage.'" width="120" height="90" layout="responsive" alt="'.$image[$k]['gallery_caption_regional'].'">
					</amp-img>
					</span>';

				if($k>=2){
                                        break;
                                }

			}
		}

		$content .= '</a>
			</div>
			</figure>';

		return $content;
	}

	public function solrToApi($api){

		foreach($api as $k=>$v){
			$data[$k]['categorybadgeslug'] = $v['category'];
			$data[$k]['categorybadge_regional'] = $v['categoryLanguage'];
			$data[$k]['title_regional'] = $v['headline'];
			$data[$k]['contenttype'] = $v['contentType'];
			$data[$k]['titleslug'] = $v['titleSlug'];
			$data[$k]['title'] = $v['headline'];
			$data[$k]['image_complete'] = $v['featuredImg'];
			$data[$k]['url_complete'] = $v['shareUrl'];
		}
		
		return $data;
	}

	public function getGridView($data){

		global $domainName;

		$content .= '<section class="grid-view">
			<amp-carousel class="" layout="fixed-height" type="carousel" height="183">';

		foreach($data as $k => $v){

			if(isset($v[0])){
				$v = $v[0];
			}

			if($v['contenttype']=="imagegallery"){
                                $icon = "photo_story";
                        }elseif($v['contenttype']=="video"){
                                $icon = "video_story";  
                        }else{
                                $icon = "";
                        }

			
			if(isset($v['image_complete'])){
				$featuredImage = $v['image_complete'];
			}else{
				$imageServerPathCon = getTheImageServerPath($v['image_url']);
                                $featuredImage = imageResizer($v['image_url'], $imageServerPathCon, '300x225');
			}

			if(isset($v['url_complete'])){
				$url = $v['url_complete'];
			}else{
				$url = getPermalink($v);
			}

			$content .= '<figure class="grid-container">
				<a class="imgwrap '.$icon.'" href="'.$url.'">
				<amp-img width="180" height="135" layout="responsive" src="'.$featuredImage.'" alt="'.$v['title_regional'].'"></amp-img>
				</a>
				<figcaption>
				<h2>
				<a class="category-badge" href="'.$domainName.'/'.$v['categorybadgeslug'].'/">'.$v['categorybadge_regional'].': </a> <a href="'.$url.'">'.$v['title_regional'].'</a>
				</h2>
				</figcaption>
				</figure>';
		}

		$content .= '</amp-carousel>
			</section>';

		return $content;
	}

	public function getListView($data){

		global $domainName;

		$content = '<section class="list-view">';

		foreach($data as $k=>$v){

			if(isset($v[0])){
				$v= $v[0];
			}

			if($v['contenttype']=="imagegallery"){
                                $icon = "photo_story";
                        }elseif($v['contenttype']=="video"){
                                $icon = "video_story";  
                        }else{
                                $icon = "";
                        }

			if(isset($v['image_complete'])){
                                $featuredImage = $v['image_complete'];
                        }else{
                                $imageServerPathCon = getTheImageServerPath($v['image_url']);
                                $featuredImage = imageResizer($v['image_url'], $imageServerPathCon, '300x225');
                        }

                        if(isset($v['url_complete'])){
                                $url = $v['url_complete'];
                        }else{
                                $url = getPermalink($v);
                        }


			$content .= '<figure>
				<a href="'.$url.'" class="imgwrap '.$icon.'" title="'.$v['title'].'">
				<amp-img width="120" height="90" layout="responsive" src="'.$featuredImage.'" title="'.$v['title'].' - Source | Latest national News at Lokmat.com" alt="'.$v['title_regional'].'- Marathi News | '.$v['title'].' - Source | Latest national News at Lokmat.com"></amp-img>
				</a>
				<figcaption>
				<h2>
				<a class="list-category" href="'.$domainName.'/'.$v['categorybadgeslug'].'/">'.$v['categorybadge_regional'].':</a>
				<a href="'.$url.'">'.$v['title_regional'].'</a>
				</h2>
				</figcaption>
				</figure>'; 

		}

		$content .= '</section>';

		return $content;
	}

	public function getCarousal($data){

		global $domainName;
		$contentAmp = '';
		$contentAmp .= '<amp-carousel class="featured-carousel" layout="fixed-height" type="carousel" height="253">';
		foreach($data as $k => $v){

			if(isset($v[0])){
				$v= $v[0];
			}

			if($v['contenttype']=="imagegallery"){
				$icon = "photo_story";
			}elseif($v['contenttype']=="video"){
				$icon = "video_story";	
			}else{
				$icon = "";
			}

			$imageServerPathCon = getTheImageServerPath($v['image_url']);
			$featuredImage = imageResizer($v['image_url'], $imageServerPathCon, '300x225');
			$contentAmp .= '<figure  class="lead-story">
				<figcaption>
				<h2>
				<a title="'.$v['categorybadge_regional'].'" class="category-badge" href="'.$domainName.'/'.$v['categorybadgeslug'].'/"> '.$v['categorybadge_regional'].' :</a>
				<a href="'.getPermalink($v).'">'.$v['title_regional'].'</a>
				</h2>
				</figcaption>
				<a class="imgwrap '.$icon.'" href="'.getPermalink($v).'" title="'.$v['title'].'">
				<amp-img width="420" height="315" layout="responsive" src="'.$featuredImage.'" title="'.$v['title_regional'].'"></amp-img>
				</a>
				</figure>';
		}
		$contentAmp .= '</amp-carousel>';

		return $contentAmp;
	}

	public function getCategoryContent($categoryArticles, $category){

		global $domainName;

		$contentAmp = "";
		$contentAmp .= '<div  class="category-head">
			<h1>'.ucfirst($category).'</h1>
			<div class="category-option">';
		/*<div class="cat-strip left-cat">
		  <a href="http://epaper.lokmat.com/main-editions/Mumbai%20Main%20/-1/1?utm_source=Lokmat.com&amp;utm_medium=referral" target="_blank">Epaper</a>
		  <a href="http://epaper.lokmat.com/sub-editions/Hello%20Mumbai/-1/1?utm_source=Lokmat.com&amp;utm_medium=referral" target="_blank">Hello Mumbai</a>
		  </div>*/
		$contentAmp .= '<div class="cat-strip right-cat">
			<a href="'.$domainName.'/photos/'.$category.'/">Photos</a>
			<a href="'.$domainName.'/videos/'.$category.'/">Videos</a>
			</div>
			</div>
			</div>';

		foreach($categoryArticles as $k => $v){

			$categoryRegional = $v['categorybadge_regional'];

			if($k==0){

				$imageServerPathCon = getTheImageServerPath($v['image_url']);
				$featuredImage = imageResizer($v['image_url'], $imageServerPathCon, '300x225');

				$contentAmp .= '<figure  class="lead-story">
					<figcaption>
					<h2>
					<a title="" class="category-badge" href="'.$domainName.'/'.$category.'/">'.$categoryRegional.':</a>
					<a href="'.$domainName.'/'.$category.'/'.$v['titleslug'].'">'.$v['title_regional'].'</a>
					</h2>
					</figcaption>
					<a class="imgwrap" href="'.$domainName.'/'.$category.'/'.$v['titleslug'].'" title="'.$v['title'].'">
					<amp-img width="420" height="315" layout="responsive" src="'.$featuredImage.'" title="'.$v['title_regional'].'" alt="'.$v['title_regional'].'"></amp-img>
					</a>
					</figure>';
			}else{

				$imageServerPathCon = getTheImageServerPath($v['image_url']);
				$featuredImage = imageResizer($v['image_url'], $imageServerPathCon, '300x225');

				$contentAmp .= '<section class="list-view">
					<figure>
					<a href="'.$domainName.'/'.$category.'/'.$v['titleslug'].'" class="imgwrap" title="'.$v['title'].'" >	  <amp-img width="120" height="90" layout="responsive" src="'.$featuredImage.'" title="'.$v['title'].'- Source | Latest national News at Lokmat.com" alt="'.$v['title_regional'].'- Marathi News | '.$v['title'].'- Source | Latest national News at Lokmat.com"></amp-img>
					</a>
					<figcaption>
					<h2>
					<a class="list-category" href="'.$domainName.'/'.$category.'/">'.$categoryRegional.':</a>
					<a href="'.$domainName.'/'.$category.'/'.$v['titleslug'].'">'.$v['title_regional'].'</a>
					</h2>
					</figcaption>
					</figure>
					</section>';
			}

		}

		return $contentAmp;

	}

	public function getMobileMenu($query, $client, $params, $data){

		$menuCollection = array();
		$sQuery = 'contenttype:"menu" AND menutype_txt: "mobile" AND status:active';
		$aSorts = array('order' => 'asc');
		$sEndpoint = "lokmat";
		$aFields = array("*");
		$iArticleLimit = 100;
		$iPage = 1;

		$solrData = array();
		$solrData = $query->getSimpleSelect($client, $sEndpoint, $sQuery, $aFields, $aSorts, $iArticleLimit, $iPage);

		//print_r($solrData);

		foreach($solrData[0] as $k => $v){

			if($v['parentflag']=='true' || $v['parentflag']=='TRUE' || $v['parentflag']==1){
				$menuCollection['child'][] = $v;
			}
			else{
				$menuCollection['parent'][] = $v;
			}
		}

		$parentChildMap = array();
		foreach($menuCollection['parent'] as $parentOrder => $parent){
			foreach($menuCollection['child'] as $k => $child){
				if($parent['dbid'] == $child['parentid']){
					$parentChildMap[$parentOrder]['parent'] = $parent['menu'];
					$parentChildMap[$parentOrder]['child'][] = $child['menu'];
				}else{
					$parentChildMap[$parentOrder]['parent'] = $parent['menu'];
				}
			}
		}

		//print_r($menuCollection);
		print_r($parentChildMap);



		die("Mobile Menu");

	}

	public function getFeaturedImage($data){
		global $imageServerPath, $defaultImage;
		$featuredImageHtml = $featuredImage = "";

		//get the featured Image
		if (checkIsset('image_url', $data)) {
			$imageServerPathCon = getTheImageServerPath($data['image_url']);
			$featuredImage = imageResizer($data['image_url'], $imageServerPathCon, '300x225');
		}
		else {
			$featuredImage = $imageServerPath . '300x225/' . $defaultImage;
		}

		//Display Featured Image
		if ($data['contenttype'] != 'imagegallery' && $data['contenttype'] != 'video') {

			$featuredImageHtml .= '
				<figure class="featured-img">
				<amp-img src="' . $featuredImage . '" width="300" height="225" layout="responsive"></amp-img>
				</figure>';
		}

		return array($featuredImageHtml, $featuredImage);
	}

	public function getTheAmpContent($data){
		$ampContent = "";
		if (!empty($data['content'])) {

			/*if($data['contenttype']=='imagegallery'){
			  die("Please check your internet connection..");
			  }*/

			if ($data['contenttype'] == 'video' && isset($data['videourl'])) {
				$ampContent = $data['cleancontent'];
			}
			else {
				$ampContent = $this->ampFormat($data['content']);
			}
			//Ads Commented due to auto ads
			$ampContent = $this->adCodeInjuction($ampContent);
		}
		return $ampContent;
	}

	public function getTheAmpLiveblogContent($data){
		global  $imageFeaturedSizeAmp;
		$ampContentLiveblog = $liveblogContent = "";

		/*$redisObject = redisConnect();
		  if($redisObject->exists("liveblogcontent_data_".$data['utslug'])){
		  $liveblogContent=json_decode($redisObject->get("liveblogcontent_data_".$data['utslug']),true);
		  }*/

		if($data['contenttype']=="liveblog" && !empty($liveblogContent))
		{
			$xmlstrPost=$xmlstrPostCss=$xmlstrContent="";
			$xmlstrPost .=' <amp-live-list layout="container"
				data-poll-interval="15000"
				data-max-items-per-page="5"
				id="amp-live-list-insert-blog">
				<button update on="tap:amp-live-list-insert-blog.update" class="ampstart-btn ml1 caps">         
				<span class="blogUpdateText">You have updates</span>
				</button>
				<div items>';
			$title=$data['title'];


			foreach($liveblogContent as $key=>$value)
			{
				$sTitleRegional="";
				//$contentImage=$featuredImage;
				$contentImage="";
				preg_match('/src="([^"]+)"/',$value['content'], $img_tag);
				if(!empty($img_tag[1]))
				{
					if (preg_match('/\.(jpeg|jpg|png|gif)$/i', $img_tag[1])) {
						$imageServerPathCon = getTheImageServerPath($img_tag[1]);
						$contentImage = imageResizer($img_tag[1], $imageServerPathCon, $imageFeaturedSizeAmp);
					}

				}

				if(isset($value["title_regional"]) && !empty($value["title_regional"])){
					$sTitleRegional = $value["title_regional"];
				}

				$xmlstrContent = $this->ampFormat($value['content']);
				$time=strtotime($value['pubdate']);

				if(!empty($xmlstrContent)){
					$sPubDate       = $value['pubdate'];
					$sTime          = date('h:i A', strtotime($sPubDate));
					$sTimeStamp     = strtotime($sPubDate);

					$xmlstrPost .= '<div id='.$value['dbid'].'
						data-sort-time="'.$time.'" data-update-time="'.$time.'">
						<div class="card blog">
						<p class="date">'.$sTime.'</p>';
					if(!empty($sTitleRegional))
					{
						$xmlstrPost .= '<h4 class="title">'. $sTitleRegional.'</h4>';
					}
					if(!empty($contentImage)){
						$xmlstrPost .= '<amp-img src="'.$contentImage.'"
							layout="responsive"
							width="300"
							height="250">
							</amp-img>';
					}
					$xmlstrPost .=' <p>'.$xmlstrContent.'</p>
						</div>
						</div>';
				}


			}//end of foreach


			$xmlstrPost .=    '</div>
				</amp-live-list>';
			$ampContentLiveblog .= $xmlstrPostCss.$xmlstrPost;
		}//end of liveblog if condition 
		return $ampContentLiveblog;
	}

	public function getTheAmpGalleryContent($data){
		global $imageServerPath, $imageFeaturedSizeAmp;
		$ampContentGallery = "";
		if ($data['contenttype'] == 'imagegallery' && $data['gallery_url'] != "") {

			//$ampContentGallery .= '<div class="article-content article-photo">';
			$xmlstrGalleryMeta = "";
			foreach ($data['gallery_url'] as $key => $images) {

				$size = '400x300';
				if(isset($data['gallery_width']) && isset($data['gallery_height'])){
					$size = $data['gallery_width'][$key].'x'.$data['gallery_height'][$key];
				}

				if(!empty($data['gallery_caption_regional'][$key])){
					$xmlstrGalleryMeta = $data['gallery_caption_regional'][$key];
				}
				elseif(!empty($data['gallery_description'][$key])){
					$xmlstrGalleryMeta = $data['gallery_description'][$key];
				}
				elseif(!empty($data['gallery_caption'][$key])){
					$xmlstrGalleryMeta = $data['gallery_caption'][$key];
				}
				elseif(!empty($data['gallery_alt_regional'][$key])){
					$xmlstrGalleryMeta = $data['gallery_alt_regional'][$key];
				}
				elseif(!empty($data['gallery_alt'][$key])){
					$xmlstrGalleryMeta = $data['gallery_alt'][$key];
				}
				elseif(!empty($data['gallery_title'][$key])){
					$xmlstrGalleryMeta = $data['gallery_title'][$key];
				}

				$xmlstrGalleryMeta = preg_replace('/"/m', '\'', $xmlstrGalleryMeta);

				//print_r($size);die("+++");
				$imageServerPathCon = getTheImageServerPath($images);
				$ampContentGallery .= '<figure>';
				$ampContentGallery .= '<amp-img src="' . imageResizer($images, $imageServerPathCon, $size, TRUE) . '" width="400" height="300" alt="' .$xmlstrGalleryMeta . '" layout="responsive"></amp-img>';
				$ampContentGallery .= '<figcaption>'.$xmlstrGalleryMeta.'</figcaption></figure>';


			}

			//$ampContentGallery .= "</div>";

		}
		return $ampContentGallery;
	}

	public function getTheAmpvideoContent($data){
		$ampContentVideo = "";
		if ($data['contenttype'] == 'video' && isset($data['videourl'])) {
			$ampContentVideo .= $this->ampFormat($data['videourl']);
		}
		return $ampContentVideo;
	}

	public function getTagsAmp($data){
		$tagsAmpHtml="";

		if (isset($data['primarytagslug'])) {

			$tagsAmpHtml .='<div class="tags">';
			$tagsAmpHtml .='<span class="title">टॅग्स :</span> <span class="taglist">';

			$primaryTag = $data["primarytag"];
			if(isset($data["primarytag_regional"]) && !empty($data["primarytag_regional"])){
				$primaryTag = $data["primarytag_regional"];
			}

			$tagsAmpHtml .='<a class="tagitem" href="/topics/'.$data['primarytagslug'].'/">'.$primaryTag.'</a>';

			if (isset($data['tagslug'])) {
				foreach($data['tagslug'] as $k=>$v){
					if($data['primarytagslug']!=$v){
						$tag = $data["tag"][$k];
						if(isset($data["tag_regional"][$k]) && !empty($data["tag_regional"][$k])){
							$tag = $data["tag_regional"][$k];
						}

						$tagsAmpHtml .='<a class="tagitem" href="/topics/'.$data['tagslug'][$k].'/">'.$tag.'</a>';
					}
				}
			}
			$tagsAmpHtml .= '</span></div>';

		}

		return $tagsAmpHtml;
	}

	public function getSocialWrapAmp($data){
		global $domainUrl, $fbAppId, $siteName;

		$socialWrapAmpHtml = "";

		$socialWrapAmpHtml .= '<div class="social-share">';
		$socialWrapAmpHtml .= '<amp-social-share width="40" height="30" type="whatsapp" data-param-text="'.$data['title_regional'].' - '.getPermalink($data).'"></amp-social-share>';
		$socialWrapAmpHtml .= '<amp-social-share type="facebook" width="40" height="30" data-param-text="'.$data['title_regional'].'" data-param-href="'.getPermalink($data).'" data-param-app_id="' . $fbAppId[$siteName] . '"></amp-social-share>';
		$socialWrapAmpHtml .= '<amp-social-share type="twitter" width="40" height="30"></amp-social-share>';
		$socialWrapAmpHtml .= '<amp-social-share type="linkedin" width="40" height="30" data-param-text="'.$data['title_regional'].'" data-param-url="e on LinkedIn"></amp-social-share>';
		$socialWrapAmpHtml .= '<amp-social-share type="email" width="40" height="30"></amp-social-share>';
		$socialWrapAmpHtml .= '</div>';

		return $socialWrapAmpHtml;
	}

	public function setAmpContent($data,$sIsBotRequest = ""){
		global $basePath, $twitterSite, $domainUrl, $copyright, $copyrightDisclaimer, $ogSiteName, $siteName, $fbAppId, $lokmatLogo, $gaCode, $categoryLinks, $xmlStaticData, $imageServerPath, $imageFeaturedSize, $imageThumbSize, $imageFeaturedSizeExplode, $imageThumbSizeExplode, $defaultImage;

		$preBody = $contentAmpHtml = $featuredImageHtml = $contentAmp = $contentLiveblogAmp = $contentGalleryAmp = $contentVideoAmp = $tagsHtml = "";

		$authorSlug=isset($data['authorslug']) && !empty($data['authorslug'])?$data['authorslug']:"";
		$pubDate=isset($data['pubdate']) && !empty($data['pubdate'])?date('D,  F d, Y g:ia', strtotime($data['pubdate'])):"";	
		$modDate=isset($data['moddate']) && !empty($data['moddate'])?date('D,  F d, Y g:ia', strtotime($data['moddate'])):"";	

		$preBody .= '<h1 class="article-head">' . checkLanguage("title", $data) . '</h1>';
		$preBody .= '<p class="publisher"> By <a href="/author/' .$authorSlug. '/">' . checkLanguage('author', $data) . '</a> | Published: <span class="published-time">'.$pubDate.'</span>  | Updated: <span class="updated-time">'.$modDate.'</span></p>';

		if ($data['contenttype'] != 'imagegallery' && $data['contenttype'] != 'video') {
			$preBody .= '<h2 class="article-description">'.getTheExcerpt($data).'</h2>';
		}

		$preBody .= '<a class="openapp-top" href="https://www.lokmat.com/install_app.htm?utm_source=Lokmat.com&utm_medium=app_banner_amp_BeforeArticle&launch_url='.getPermalink($data).'" class="openinApp1">Open in App</a>';

		$featuredImageHtml = $this->getFeaturedImage($data)[0];
		$socialShare = "";
		$socialShare = $this->getSocialWrapAmp($data);
		$contentAmp = $this->getTheAmpContent($data);
		$contentLiveblogAmp = $this->getTheAmpLiveblogContent($data);
		$contentGalleryAmp = $this->getTheAmpGalleryContent($data);
		$contentVideoAmp = $this->getTheAmpvideoContent($data);	
		//$contentVideoAmp = "";
		$tagsHtml = $this->getTagsAmp($data);
		$formatedContent = $contentAmp;

		//check the contenttype and assign the content
		if($data['contenttype']=='article'){
			$contentClass = "";
			$formatedContent = $contentAmp;
		}elseif($data['contenttype']=='imagegallery'){
			$contentClass = "article-photo";
			$formatedContent = $contentGalleryAmp;
		}elseif($data['contenttype']=='video'){
			$formatedContent = $contentVideoAmp;
			$contentClass = "article-video";
		}elseif($data['contenttype']=='liveblog'){
			$formatedContent = $contentLiveblogAmp;
			$contentClass = "";
		}


		/**
		 * Get the Highlights	
		 */
		$highlights = "";
		if( isset($data['highlights']) ){
			$highlights .= '<div class="highlights-box"> 
				<span class="highlight-head">ठळक मुद्दे</span>
				<span class="highlight-list">';
			foreach($data['highlights'] as $k=>$v){
				$highlights .= '<span class="highlight-news">'.$v.'</span>';			
			}

			$highlights .= '</span>
				</div>';
		}


		/**
		 * Red Strip
		 */ 
		$redStrip = '<!-- open in app red strip -->
			<a href="'.getPermalink($data).'?utm_source=Lokmat.com&amp;utm_medium=app_banner_amp_AfterArticle&amp;launch_url='.getPermalink($data).'" class="openapp-below">Open in App <span class="arrowRight">→</span></a>';

		$contentAmpHtml .= $preBody.$featuredImageHtml.$socialShare.'<div class="article-content '.$contentClass.'">'.$highlights.$formatedContent.$contentLiveblogAmp.$contentGalleryAmp.$contentVideoAmp.'</div>';


		/**
		 * Set Amp data in Redis
		 */
		/*$redisObject = redisConnect();
		  $iSixMonthExpTime = 172800;
		  if(!empty($sIsBotRequest)){
		  $iSixMonthExpTime = 60;
		  }*/

		if(isset($data["pubdate"]) && !empty($data["pubdate"]))
		{
			$pubDate = date("Y-m-d", strtotime($data["pubdate"])) . "T" . date("H:i:s", strtotime($data["pubdate"])). "+05:30";
		}else{
			$pubDate ="";
		}

		if(isset($data["moddate"]) && !empty($data["moddate"]))
		{	
			$modDate = date("Y-m-d", strtotime($data["moddate"])) . "T" . date("H:i:s", strtotime($data["moddate"])). "+05:30";
		}else{
			$modDate ="";
		}

		if(!isset($data['meta_keyword'])){
			$data['meta_keyword']="";	
		}
		$ampCollection = array('meta_description'=> getTheExcerpt($data), 'meta_image'=>$this->getFeaturedImage($data)[1], 'meta_keywords'=>$data['meta_keyword'], 'urlslug'=>getPermalink($data), 'pubdate'=>$pubDate, 'moddate'=>$modDate, 'author'=>$authorSlug, 'jsonLdContent'=>'', 'tags_amp'=> $tagsHtml, 'canonical'=>getPermalink($data), 'redStrip'=>$redStrip);
		$key = $data['contenttype'].'_amp_collection_'.$data['utslug'];
		//$redisObject->set("check", "OK");

		//print_r($key);
		//die("+++++");
		//print_r($ampCollection);die("+++++++++++++++");
		//print_r($contentAmpHtml);die("--------------");

		/*
		   $redisObject->set($key, json_encode($ampCollection));
		   $redisObject->expire($key, $iSixMonthExpTime);

		   $key = $data['contenttype'].'_content_amp_'.$data['utslug'];
		   $redisObject->set($key, $contentAmpHtml);
		   $redisObject->expire($key, $iSixMonthExpTime);
		 */

		//print_r($contentAmpHtml);die();
		$data['ampcontent'] = $contentAmpHtml;
		$data['ampcollection'] = $ampCollection;
		return $data;
		//return $contentAmpHtml;
	}

	public function adCodeInjuction($clearContent) {

		global $ampAds;

		$expClearContent = explode("</p>", $clearContent);
		$i = 0;
		$newContent = array();
		foreach ($expClearContent as $key => $expContent) {

			if (!empty($expContent) && $expContent != "" && strchr($expContent, "<p>")) {
				$wordCount = explode(" ", $expContent);

				if (count($wordCount) >= $ampAds['lokmat']['adpos']) {

					if ($i % 2 == 0) {
						$adCode="";
						if(isset($ampAds['lokmat']['ad'][$i])){
							$adCode = $ampAds['lokmat']['ad'][$i];
						}
						else{
							$adCodeRandom = "";
							$adCodeRandom = array_rand($ampAds['lokmat']['ad'], 1);
							$adCode = $ampAds['lokmat']['ad'][$adCodeRandom];
						}

						$newContent[] = $expContent . '</p>' . $adCode;

					}
					else {
						$newContent[] = $expContent . "</p>";
					}

					++$i;
				}
				else {
					$newContent[] = $expContent . "</p>";
				}
			}
			else {
				$newContent[] = $expContent;
			}
		}

		$newContent = implode("", $newContent);
		return $newContent;
	}

	public function ampFormat($content) {

		$imageFeaturedSizeAmp = "320x250";
		//$imageFeaturedSizeAmp = "1200x900";

		global $imageServerPath, $imageFeaturedSizeAmp;

		// print_r($content);die("Please check your internet connection..");
		//youtube replace
		$re = '/{{{{youtube_video_id####([^(\?|})]*)(?:[^}]*)}}}}/m';
		$subst = '<amp-youtube data-videoid="$1" layout="responsive" width="480" height="270"></amp-youtube>';
		$content = preg_replace($re, $subst, $content);

		//facebook video replace
		$re = '/{{{{facebook_video_id####(.+videos[^}]*)}}}}/m';
		$subst = '<amp-facebook width="552" height="310" layout="responsive" data-embed-as="video" data-href="$1"></amp-facebook>';
		$content = preg_replace($re, $subst, $content);

		//facebook replace
		$re = '/{{{{facebook_post_id####([^}]*)}}}}/m';
		$subst = '<amp-facebook width="552" height="310" layout="responsive" data-embed-as="post" data-href="$1"></amp-facebook>';
		$content = preg_replace($re, $subst, $content);

		//twitter post replace
		$re = '/{{{{twitter_post_id####.+twitter\.com\/.+\/status\/([0-9]+)[^}]*}}}}/m';
		$subst = '<amp-twitter width="375" height="472" layout="responsive" data-tweetid="$1"></amp-twitter>';
		$content = preg_replace($re, $subst, $content);

		//twitter video replace
		$re = '/{{{{twitter_video_id####.+twitter\.com\/.+\/status\/([0-9]+)[^}]*}}}}/m';
		$subst = '<amp-twitter width="375" height="472" layout="responsive" data-tweetid="$1"></amp-twitter>';
		$content = preg_replace($re, $subst, $content);

		//instagram replace
		//$re = '/{{{{instagram_id####https:\/\/www\.instagram\.com\/p\/([^(\/|})]*)\/?}}}}/m';
		//$re = '/{{{{instagram_id####https:\/\/www\.instagram\.com\/p\/((.|\n)*?)\/(.|\n)*?}}}}/m';
		$re = '/{{{{instagram_id####https:\/\/www\.instagram\.com\/p\/(.*?)\/.*?}}}}/m';
		$subst = '<amp-instagram data-shortcode="$1" data-captioned width="400" height="400" layout="responsive"></amp-instagram>';
		$content = preg_replace($re, $subst, $content);

		//replace img tag with amp-img
		$re = '/<img(.*?)>/m';
		$subst = '<amp-img$1 layout="responsive"></amp-img>';
		$content = preg_replace($re, $subst, $content);

		//brightcove video replace
		$re = '/<iframe.*players.brightcove.net\/(.*?(?=\/))\/.*?(?=videoId)videoId=(.*?(?=")).*?(?=<\/iframe>)<\/iframe>/';
		$content = preg_replace($re, $subst, $content);

		//replace image src
		$re = '/((<amp-img.*?src.*?[\'|"])(.*?)([\'|"].*?\/amp-img>))/m';
		if (preg_match_all($re, $content, $matches, PREG_SET_ORDER, 0)) {
			foreach ($matches as $matchImg) {
				$imageServerPathCon = getTheImageServerPath($matchImg[3]);
				$content = str_replace($matchImg[1], '<amp-img src="' . imageResizer($matchImg[3], $imageServerPathCon, $imageFeaturedSizeAmp) . '" layout="responsive" width="400" height="300" ></amp-img>', $content);
			}
		}

//remove style attribute from content
$re = '/(type="[^"]*"|style="[^"]*")/';
$subst = '';
$content = preg_replace($re, $subst, $content);

//$content = preg_replace('/<iframe>((.|)*?)<\/iframe>/m', '<amp-iframe>$1</amp-iframe>', $content);
//$content = preg_replace('/<iframe((.|\n)*?)<\/iframe>/m', '<amp-iframe layout="responsive" width=100 height=300 $1</amp-iframe>', $content);

//$content = preg_replace('/<iframe((.|\n)*?)<\/iframe>/m', '<amp-iframe layout="responsive" width=100 height=300 $1</amp-iframe>', $content);

//$content = preg_replace('/src="(.*?)"/m', $content, 'src="$1#amp=1"');

//remove iframe code
$re = '/<iframe.*?(?=<\/iframe>)<\/iframe>/m';
$subst = '';
$content = preg_replace($re, $subst, $content);

//remove font tag
$re = '/<\/?(font)[^>]*>/m';
$subst = '';
$content = preg_replace($re, $subst, $content);

//remove break tag <br />
$re = '/<br.*?(?=\/>)\/>/m';
$subst = '';
$content = preg_replace($re, $subst, $content);

/*
   $re = '/<script async defer src="\/\/www\.instagram\.com\/embed\.js"><\/script>/m';
   $content = preg_replace($re, '', $content);

   $re = '/<script async.*? src="\/\/www\.instagram\.com\/embed\.js"><\/script>/m';
   $content = preg_replace($re, '', $content);
 */

$content = preg_replace('/<script(.*?)>(.*?)<\/script>/mi', '', $content);

$content = preg_replace('/<script>(?:.*|\n)*<\/script>/mi','', $content);

$content = preg_replace('/javascript:void\(0\)/m', '', $content);
//print_r($content);

//$content = preg_replace('/<iframe>((.|)*?)<\/iframe>/m', '<amp-iframe>$1</amp-iframe>', $content);
//$content = preg_replace('/<iframe((.|\n)*?)<\/iframe>/m', '<amp-iframe$1</amp-iframe>', $content);

//print_r($content); 
//die("Please cehck your internet connection....");
return $content;
}


public function getRelatedIds($data){

	$relatedIds = array();
	if(isset($data['related'])){
		if(!empty($data['related'])){
			foreach($data['related'] as $related){
				$relatedIds[] = $related;
			}
		}
	}

	$output = array();
	$related = array_filter($relatedIds);
	if(!empty($related)){
		if(count($related)>0){
			$output['relatedids'] = $related;
			$related = implode(' OR ',$related);
			$output['query'] = ' AND (dbid:('.$related.'))';
		}
		else{
			$related = implode(',',$related);
			$output['relatedids'] = $related;
			$output['query'] = ' AND (dbid:('.$related.'))';
		}
	}

	//print_r($related);die("Related");
	return $output;

}


public function getCatIds($data){

	$tagPrimaryId = array();
	if(isset($data['categorybadgeslug'])){
		if(!empty($data['categorybadgeslug'])){
			$tagPrimaryId[] = $data['categorybadgeslug'];
		}
	}


	$tagIds = array();
	if(isset($data['categoryslug'])){
		if(!empty($data['categoryslug'])){
			foreach($data['categoryslug'] as $tag){
				$tagIds[] = $tag;
			}
		}
	}

	$tags = array_merge($tagPrimaryId, $tagIds);

	$output = array();
	$tags = array_filter($tags);
	if(!empty($tags)){
		if(count($tags)>0){
			$output['catids'] = $tags;
			$tags = implode(' OR ',$tags);
			$output['query'] = ' AND (categorybadgeslug:('.$tags.') OR categoryslug:('.$tags.'))';
		}
		else{
			$tags = implode(',',$tags);
			$output['catids'] = $tags;
			$output['query'] = ' AND (categorybadgeslug:('.$tags.') OR categoryslug:('.$tags.'))';
		}
	}

	return $output;

}
public function getTagIds($data){

	$tagPrimaryId = array();
	if(isset($data['primarytagslug'])){
		if(!empty($data['primarytagslug'])){
			$tagPrimaryId[] = $data['primarytagslug'];
		}
	}


	$tagIds = array();
	if(isset($data['tagslug'])){
		if(!empty($data['tagslug'])){
			foreach($data['tagslug'] as $tag){
				$tagIds[] = $tag;
			}
		}
	}

	$tags = array_merge($tagPrimaryId, $tagIds);

	$output = array();
	$tags = array_filter($tags);
	if(!empty($tags)){
		if(count($tags)>0){
			$output['tagids'] = $tags;
			$tags = implode(' OR ',$tags);
			$output['query'] = ' AND (primarytagslug:('.$tags.') OR tagslug:('.$tags.'))';
		}
		else{
			$tags = implode(',',$tags);
			$output['tagids'] = $tags;
			$output['query'] = ' AND (primarytagslug:('.$tags.') OR tagslug:('.$tags.'))';
		}
	}

	return $output;

}

public function relatedArticles($query, $client, $params, $data){

	global $excludeRelatedArticles;

	$excludeRelatedArticles = array();

	$sQuery = "";
	if(!empty($this->getRelatedIds($data))){
		$sQuery = $this->getRelatedIds($data)['query'];
	}
	elseif(!empty($this->getTagIds($data))){
		$sQuery = $this->getTagIds($data)['query'];
	}
	else{
		return "";
	}
	/*elseif(!empty($this->getCatIds($data))){
	  $sQuery = $this->getCatIds($data)['query'];
	  }*/	


	$sQuery = '-dbid:'.$data['dbid'].$sQuery.' AND source:"lokmat" AND status:"published" AND -redirecturl:* AND -genuine_gallery:false AND contenttype:("article" OR "imagegallery" OR "video" OR "blog")';
	$aSorts = array('moddate' => 'desc');
	$sEndpoint = "lokmat";
	$aFields = array("*");
	$iArticleLimit = 5;
	$iPage = 1;

	$solrData = array();
	$solrData = $query->getSimpleSelect($client, $sEndpoint, $sQuery, $aFields, $aSorts, $iArticleLimit, $iPage);

	$xmlstr = "";


	$relatedArticlesQuery = array();	
	if(!empty($solrData[0])){
		global $imageServerPath, $defaultImage, $relatedArticlesQuery;
		$xmlstr .= '<section class="lkm-widget"">
			<h3 class="widget-head"> संबंधित बातम्या  </h3>
			<section class="grid-view">  
			<amp-carousel class="" layout="fixed-height" type="carousel" height="183">';

		foreach($solrData[0] as $k => $relArticles){

			$relatedArticlesQuery[] = $relArticles['dbid'];

			//get the featured Image
			$featuredImage = "";
			if (checkIsset('image_url', $relArticles)) {
				$featuredImage = imageResizer($relArticles['image_url'], $imageServerPath, '320x250');
			}
			else {
				$featuredImage = $imageServerPath . '320x250/' . $defaultImage;
			}

			$xmlstr .= '<figure class="grid-container">
				<a class="imgwrap" href="' . getPermalink($relArticles) . '" target="_blank">
				<amp-img width="180" height="135" layout="responsive" src="' . $featuredImage . '" alt=""></amp-img>
				</a>
				<figcaption>
				<h2>

				<a class="category-badge" href="#">' . checkLanguage('categorybadge', $data) . ' <a href="' . getPermalink($relArticles) . '">'.  checkLanguage("title_regional", $relArticles) . '</a>

				</h2>
				</figcaption>
				</figure>';

		}

		$xmlstr .= ' </amp-carousel></section></section>';
	}


	$excludeRelatedArticles = $relatedArticlesQuery;
	return $xmlstr;

}


public function relatedArticlesCat($query, $client, $params, $data){

	$sQuery = "";

	if(!empty($this->getCatIds($data))){
		$sQuery = $this->getCatIds($data)['query'];
	}	

	global $excludeRelatedArticles;

	$excludedbid = $data['dbid'];
	if(!empty($excludeRelatedArticles)){
		$excludedbid = implode(' OR ',$excludeRelatedArticles);
		$excludedbid = '('.$data['dbid'].' OR '.$excludedbid.')';
	}

	$sQuery = '-dbid:'.$excludedbid.$sQuery.' AND source:"lokmat" AND status:"published" AND -redirecturl:* AND -genuine_gallery:false AND contenttype:("article" OR "imagegallery" OR "video" OR "blog")';
	$aSorts = array('moddate' => 'desc');
	$sEndpoint = "lokmat";
	$aFields = array("*");
	$iArticleLimit = 5;
	$iPage = 1;

	$solrData = array();
	$solrData = $query->getSimpleSelect($client, $sEndpoint, $sQuery, $aFields, $aSorts, $iArticleLimit, $iPage);

	$xmlstr = "";
	if(!empty($solrData[0])){

		global $imageServerPath, $defaultImage;

		$xmlstr .= '<section class="lkm-widget">
			<h3 class="widget-head">' . checkLanguage('categorybadge', $data) . '  कडून आणखी   </h3>
			<section class="grid-view">
			<amp-carousel class="" layout="fixed-height" type="carousel" height="183">';

		foreach($solrData[0] as $k => $relArticles){

			//get the featured Image
			$featuredImage = "";
			if (checkIsset('image_url', $relArticles)) {
				$featuredImage = imageResizer($relArticles['image_url'], $imageServerPath, '320x250');
			}
			else {
				$featuredImage = $imageServerPath . '320x250/' . $defaultImage;
			}


			$xmlstr .= '<figure class="grid-container">
				<a class="imgwrap" href="' . getPermalink($relArticles) . '" target="_blank">
				<amp-img width="180" height="135" layout="responsive" src="' . $featuredImage . '" alt=""></amp-img>
				</a>
				<figcaption>
				<h2>

				<a class="category-badge" href="#"> ' . checkLanguage('categorybadge', $data) . '</a> <a href="' . getPermalink($relArticles) . '"> ' . checkLanguage("title_regional", $relArticles) . '</a>
				</h2>
				</figcaption>
				</figure>';


		}

		$xmlstr .= ' </amp-carousel></section></section>';

	}

	return $xmlstr;

}






}

