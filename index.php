<?php
/**
 * @brief   Get journals and display everything nicely
 *
 * @todo
 * - Handle the sticky stuff better; maybe this way
 *    <div id="stickyleft" class="small-1 columns"></div>
 *    <div id="journals" class="small-10 columns"></div>
 *    <div id="stickyright" class="small-1 columns"></div>
 * - add conditional parts to part.tpl files or something like that
 *
 * @todo  2015-08-22
 * -  Doing foreach ($journals as $j) for each view is really (!) bad
 * -  It would make much more sense to make "new" rely on the publish date
 *    instead of the fixed value in input.csv (that only works if you update
 *    daily and has some quirks)
 *
 * @author Daniel Zimmel <zimmel@coll.mpg.de>
 * @author Tobias Zeumer <tzeumer@verweisungsform.de>
 */

// Experimental - testing caching. May be nearly pointless if JT is only used in a local kiosk
require_once('./config.php'); // './' makes sure we don't go looking for config.php in the include_path
if (!isset($cfg)) {
  echo 'something is very wrong with the configuration';
  exit;
}
if ($cfg->prefs->cache_main_enable) {
  $query = (isset($_GET)) ? md5(implode('&', $_GET)) : '';
  $cachefile  = $cfg->sys->data_cache."index_$query.cache.html";

  //NOTE: file_exists() result is cached. not an issue in this case, but
  //clearstatcache() needs to be called in cases where the file might be
  //deleted between tests in the same script. unlink() updates the cache
  if (file_exists($cachefile) && file_exists($cfg->sys->data_journals.'journals.csv')) {
    if (filemtime('data/journals/journals.csv') < filemtime($cachefile)) {
      echo file_get_contents($cachefile);
      exit;
    }
    // With JournalToc Premium enabled check for json file too
    elseif ($cfg->api->jt->premium) {
      if (filemtime($cfg->api->jt->outfile) < filemtime($cachefile)) {
        echo file_get_contents($cachefile);
        exit;
      }
    }
  }
}

ob_start();
require_once($cfg->sys->basepath.'sys/class.ListJournals.php');
/* setup methods & objects */
$lister = new ListJournals($cfg);
$journals = $lister->getJournals();
$journalUpdates = $lister->getJournalUpdates();
?>
<!DOCTYPE html>
<!--[if IE 9]><html class="lt-ie10" lang="en" > <![endif]-->
<html class="no-js" lang="en" data-useragent="Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.2; Trident/6.0)">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo __('MPI JournalTouch') ?></title>
    <link rel="stylesheet" href="css/foundation.min.css" />
    <link rel="stylesheet" href="css/local.css" />
    <link rel="stylesheet" href="css/media.css" />
    <link rel="stylesheet" href="css/foundation-icons/foundation-icons.css" />
    <script src="js/vendor/modernizr.js"></script>
  </head>
<!-- tell scripts if caching of tocs is enabled -->
<body data-caching="<?php echo intval($lister->prefs->cache_toc_enable) ?>">

<!-- Navigation -->
<div class="fixed">
  <nav class="top-bar" data-topbar="" data-options="is_hover: false">
    <ul class="title-area">
      <!-- Title Area -->
      <li class="name"><h1><?php echo __('JournalTouch <em><strong>beta</strong></em> - a library service') ?></h1></li>
      <li class="toggle-topbar menu-icon"><a href="#"><span><?php echo __('menu') ?></span></a></li>
    </ul>
    <section class="top-bar-section">
      <ul class="hide-for-small">
        <li><a href="#" id="aboutLink" data-reveal-id="about" class="button radius"><i class="fi-info"></i> <?php echo __('about') ?></a></li>
      </ul>

      <!-- Right Nav Section -->
      <ul class="right">
        <li class="divider"></li>
        <?php if (count($lister->tagcloud) > 1 && $lister->prefs->menu_show_tagcloud) { ?>
        <li><a href="#" id="myTags" data-reveal-id="tagsPopover"><i class="switcher fi-pencil"></i>&#160;<?php echo __('Tags') ?></a>
        </li><?php } ?><?php if ($lister->filters) { /* show filters only if set */?>
        <li class="has-dropdown">
          <a id="filter-view" href="#"><i class="fi-filter"></i>&#160;<?php echo __('filter') ?></a>
          <ul class="dropdown">
            <li><a class="filter" id="filter-reset" href="#"><i class="fi-refresh"></i>&#160;<?php echo __('show all') ?></a></li>
            <li><a class="filter" id="topJ" href="#"><i class="fi-star"></i>&#160;<?php echo __('MPI favorites') ?></a></li>
            <li><a class="filter" id="new" href="#"><i class="fi-burst-new"></i>&#160;<?php echo __('new issues') ?></a></li>
<?php
/* read all filters from the config file */
foreach ($lister->filters as $key=>$f) {
  print '<li><a class="filter" id="filter-'.$key.'" href="#">'.__($f).'</a></li>';
}
?>
          </ul>
        </li>
        <?php } ?>
        <?php if ($lister->prefs->menu_show_sort) { ?>
            <?php
                $sort_current = ($lister->prefs->default_sort_date) ? __('A-Z') : __('Date');
                $sort_alt     = ($lister->prefs->default_sort_date) ? __('Date') : __('A-Z');
            ?>
            <li><a id="switch-sort" "href="#"><i class="switcher fi-shuffle"></i>
            <span>&#160;
            <?php
                echo __('Sort').' <span id="sort-alt" data-lang="'.$sort_alt.'">'.$sort_current.'</span>';
            ?>
            </span></a></li>
        <?php } ?>
        <?php if ($lister->prefs->menu_show_listview) { ?>
            <li><a id="switch-view" href="#"><i class="switcher fi-list"></i><span>&#160;<?php echo __('list view') ?></span></a></li>
        <?php } ?>
        <li><a href="#" id="myArticles" data-reveal-id="cartPopover"><i class="fi-shopping-bag"></i>&#160;<?php echo __('my basket') ?>(<span class="simpleCart_quantity"></span>)</a></li>
<?php
$lng_options = '';
foreach ($lister->prefs->languages as $set_lang) {
  if ($set_lang != $lister->prefs->current_lang) $lng_options .= "<li><a id=\"switch-language\" href=\"index.php?lang=$set_lang\"><img src=\"languages/$set_lang.gif\" /></a></li>";
}

// Show a drop down menu if more than two languages are available
if (count($lister->prefs->languages) > 2) {
  echo '<li class="divider"></li>
    <li class="has-dropdown switch-language">
    <a id="langauge-view" href="#"><i class="fi-flag"></i>&#160;'. __('Language').'</a>
    <ul class="dropdown">'.$lng_options.'
    </ul>
    </li>
    </ul>';
}
// Otherwise just show a simple toggle
elseif (count($lister->prefs->languages) == 2) {
  echo '<li class="divider"></li>'.$lng_options;
}
// And (implicit) nothing if only one language is available
?>
    </section>
  </nav>
</div>
<!-- End Navigation -->

<!-- Left and right side elements -->
<!--  Note
      1. For letterbox see setLetterBox() in js/local/conduit.js
      2. Most (re-)sizing is done via css/media.css               -->

  <!-- Make a sticky basket -->
  <a href="#" id="stickyBasket" class="button radius show-for-xlarge-up" data-reveal-id="cartPopover"><i class="fi-shopping-bag"></i>&#160;<?php echo __('Send articles') ?></a>

  <!-- Make a sticky GoUp -->
  <a href="#" id="stickyGoUp" class="button round"><i class="fi-arrow-up"></i></a>
<!-- END Left and right side elements -->


<!-- About page -->
<div id="about" class="reveal-modal" data-reveal="">
  <div class="row">
    <div class="small-12 medium-12 large-12 columns left">
      <h2><?php echo __('About') ?></h2>
      <p>
<?php
$lib_teaser = __("<em>JournalTouch</em> is the <strong>PLACEHOLDER's</strong> alerting service for newly published journal issues.");
$lib_teaser = str_replace('PLACEHOLDER', ' '.$lister->prefs->lib_name, $lib_teaser);
echo $lib_teaser;
?>
      </p>
      <p>
<?php echo __('It\'s easy &dash; select a journal and add interesting articles to your shopping basket. If there is an abstract
available, it will be indicated with an extra button.
When you are finished, click on your basket to check out.
You can now send the article information to your e-mail address, send a
request for the PDF files to the library, or view/save it as a
list. Export for citation management systems like Endnote is also available. <br/>
The list of journals is a selection of the journals licensed to the library.')
?>
      </p>
    </div>
  </div>
  <div class="row">
    <div class="small-8 medium-9 large-9 columns left">
      <p>
        <?php echo __('If a journal is missing, please let us know and we will add it to the list.<br/><br/> <strong><em>JournalTouch</em> is actively being developed by the library team</strong>.') ?>
        <br /><?php echo __('Tables of contents are provided by <strong>CrossRef</strong> and <strong>JournalTocs</strong>.') ?>
      </p>
    </div>
    <div class="small-4 medium-3 large-3 columns right">
      <ul class="inline-list right">
        <li><a class="button radius" href="checkout.php?action=contact&amp;lang=<?php echo $lister->prefs->current_lang ?>"><i class="fi-comment"></i> <?php echo __('Get in touch!') ?></a></li>
      </ul>
    </div>
  </div>
  <a class="close-reveal-modal button radius alert">×</a>
</div>
<!-- End Header and Nav -->


<!-- start external link -->
<div id="externalPopover" class="reveal-modal" data-reveal="">
  <h3><?php echo __('External Source') ?></h3>
  <a id="frameBack" class="button round" data-history="0" onclick="if ($(this).data('history') < history.length) history.go(-1)"><i class="fi-arrow-left"></i></a>
  <a class="close-reveal-modal button radius">×</a>
  <!-- For preventing browser history for the iframe "externalFrame" it is dynamically created in conduit.js -->
</div>
<!-- end external link -->


<!-- start Tagcloud -->
<?php if (count($lister->tagcloud) > 1 && $lister->prefs->menu_show_tagcloud) { ?>
<div id="tagsPopover" class="reveal-modal" data-reveal="">
  <h3><?php echo __('Tagcloud') ?></h3>
  <a class="close-reveal-modal button radius">×</a>
  <p><a class="filter" id="filter-reset" href="#"><i class="fi-refresh"></i>&#160;<?php echo __('show all') ?></a></p>
  <?php echo $lister->getTagcloud(); ?>
</div>
<?php } ?>
<!-- end Tagcloud -->


<div id="cartPopover" class="reveal-modal" data-reveal="">
  <div class="simpleCart_items"></div>
  <div id="cartData" class="clearfix"></div>
  <div id="shelfIsEmpty" style="display:none">
    <i class="fi-alert"></i> <?php echo __('Your basket is empty.') ?>
  </div>
  <div id="popoverButtons" class="clearfix">
    <a id="checkOutButton" href="javascript:;" class="simpleCart_checkout radius small success button"><i class="fi-share"></i> <?php echo __('Send/Save my articles') ?></a>
    <!--<a id="emptyCartButton" href="javascript:;" class="simpleCart_empty radius small alert button"><i class="fi-trash"></i> Empty my basket</a>-->
    <a id="emptyConfirmButton" class="radius small alert button" data-reveal-id="emptyConfirm"><i class="fi-trash"></i> <?php echo __('Empty my basket') ?></a>
    <a class="close-reveal-modal button radius">×</a>
  </div>
</div>
<!--End #cartPopover-->


<!-- Security confirmation on delete -->
<div id="emptyConfirm" class="reveal-modal" data-reveal="">
  <h3><?php echo __('Do you really want to empty your basket?') ?></h3>
  <a id="emptyCartButton" href="javascript:;" class="simpleCart_empty radius small alert button close-reveal-modal"> <i class="fi-trash"></i> <?php echo __('OK, empty my basket!') ?></a>
  <a id="DoNotemptyCartButton" class="radius small success button close-reveal-modal"><i class="fi-trash"></i> <?php echo __('No, keep basket!') ?></a>
</div>


<!-- First Band (Slider) -->
<?php if ($lister->prefs->show_orbit) { ?>
<div id="view-orbit">
  <div class="row">
    <div class="large-12 columns" style="padding-top:20px">
      <h3><?php echo __('The newest editions') ?></h3>
      <?php // print date('M d Y',strtotime('-7 day'))." - " .date('M d Y',strtotime('today'));  ?>
    </div>
  </div>
  <div class="row">
    <hr />
    <div class="medium-3 large-2 columns hide-for-small logo">&#160;</div>
    <div class="medium-5 large-7 small-11 columns slideshow-wrapper">
      <div class="preloader"></div>
      <ul id="myorbit" data-orbit="" data-options="animation_speed: 1000;timer_speed: 3000;bullets: false">
<?php
/* /\* see Class setup *\/ */
foreach ($journals as $j) {
  if (!empty($j['topJ'])) {
    echo '<li data-orbit-slide="headline">';
    echo '<span id="toc-'.$j['id'].'" data-issn="'.$j['id'].'" data-pubdate="'.$j['date'].'"></span>';
    echo '<img class="issn getTOC" src="'.$j['img'].'"/>';
    echo '<div class="orbit-caption">'.$j['title'].' ('.$j['date'].')</div>';
    echo '</li>';
  }
}
?>
      </ul>
    </div>
    <div class="medium-4 large-3 columns">
      <div class=" panel radius callout" id="filterPanel" style="display:none">
        <i class="fi-filter"></i>&#160; Filter active:<span id="filterPanelFilter"></span>
      </div>
    </div>
  </div>
</div>
<hr />
<?php } ?>


<!-- Three-up Content Blocks -->
<div class="row">
  <div id="TOCbox" class="small-10 medium-10 large-12 columns">
    <!-- A-Z button bar -->
    <div id="alphabet" class="button-bar">
      <ul class="button-group radius">
<?php
$alphas = range('A', 'Z');
foreach ($alphas as $letter) {
  echo '<li><a href="#" class="tiny button secondary">'.$letter.'</a></li>';
}
?>
      </ul>
    </div>
    <!-- timestamp button bar-->
    <div id="timewarps" class="button-bar">
      <ul class="button-group radius">
<?php
$timewarps = array(
  '1d' => 86400,
  '2d' => 2*86400,
  '3d' => 3*86400,
  '4d' => 4*86400,
  '5d' => 5*86400,
  '6d' => 6*86400,
  '1w' => 7*86400,
  '2w' => 14*86400,
  '3w' => 21*86400,
  '1m' => 31*86400,
  '3m' => 92*86400,
  '6m' => 183*86400,
  '1y' => 365*86400
);
foreach ($timewarps as $label => $timestamp) {
  echo '<li><a href="#" class="tiny button secondary" data-timestamp="'.( time() - $timestamp ).'">'.$label.'</a></li>';
}
?>
      </ul>
    </div>
  </div>
</div>


<div class="row">
  <div class="panel radius callout" id="filterPanel" style="display:none">
  <i class="fi-filter"></i>&#160; <?php echo __('Filter active:') ?>
  <span id="filterPanelFilter"></span></div>
</div>

<!-- Search form -->
<form id="search-form">
  <div class="row">
    <div class="small-9 medium-10 large-12 columns">
      <fieldset>
        <label class="error">
          <input id="search" type="text" placeholder="<?php echo __('Search journal') ?>" />
        </label>
        <small id="noresults" class="error" style="display:none">
          <?php echo __('No journals found') ?>
        </small>
      </fieldset>
    </div>
  </div>
</form>
<!-- End Search form -->


<!-- show updates - this is only some example code how to include the latest journal updates -->
<!-- please refer to the README on how to use it -->
<!--
<div class="row">
  <div id="updateBox" class="small-12 columns">
    <ul>
<?php
if (!empty($journalUpdates)) {
  foreach ($journalUpdates as $j) {
    print '<li><a href="#">' . $j['title'] . '</a> (last update <time class="timeago" datetime="'.$j['timestr'].'">' . $j['timestr'] . '</time>)</li>';
  }
}
?>
    </ul>
  </div>
</div>
-->

<!-- Version 3: Grid -->
<!-- Thumbnails -->
<!--
<div id="view-grid">
  <div class="row">
    <div class="small-10 columns">
      <h3><?php echo __('Browse all journals from A to Z (grid view):') ?></h3>
    </div>
  </div>
-->
  <div id="journalList" class="row gridview <?php $sortclass = ($lister->prefs->default_sort_date) ? 'datesorted' : 'azsorted'; echo $sortclass; ?>">
<?php
/* see Class setup */
foreach ($journals as $j) {
  /* convert found date of last update in the data to a timestring (gets evaluated with jquery.timeago.js) */
  $timestring = ($j['new']) ? date('c', strtotime($j['new'])) : '';
  $wF = '<time class="timeago" datetime="'.$timestring.'">'.$timestring.'</time>';

  $meta = false;

  $jtoc = 'http://www.journaltocs.ac.uk/index.php?action=tocs&issn='.$j['issn'];
  $meta = (($j['metaGotToc']) ? '<a href="'.$jtoc.'" class="button popup"><i class="'.$j['metaGotToc'].'"></i> '.__('TOC').'</a>' : '');
  $link = ($lister->prefs->inst_service) ? $lister->prefs->inst_service.$j['issn'] : '';
  $meta .= (($j['metaOnline'] && $link) ? ' <a href="'.$link.'" class="button popup"><i class="'.$j['metaOnline'].'"></i> '.__('Library').'</a>': '');
  $meta .= (($j['metaWebsite']) ? ' <a href="'.$j['metaWebsite'].'" class="button popup"><i class="fi-home"></i> '.__('Journal').'</a>': '');
  $print_meta = (($j['metaPrint']) ? 'class="'.$j['metaPrint'].'"' : "");
  $meta .= (($j['metaShelfmark']) ? ' <span class="button"><i '.$print_meta.'> '.$j['metaShelfmark'].'</i></span>' : "&nbsp;");
  $new_issues = ($j['new']) ? ' new' : '';
  $len_title = strlen($j['title']);
  $nbr_title = ($len_title < 50) ? $j['title'] : substr($j['title'], 0, strrpos($j['title'], ' ', $len_title * -1 + 50)).' ...';

  echo '<div class="listitem search-filter filter-'.$j['filter'].' '.$j['tags'].' '.$j['topJ'].$new_issues.'" data-title="'.$j['title'].'" data-issn="'.$j['id'].'" data-pubdate="'. strtotime($j['date']).'">';
  echo '<span id="toc-'.$j['id'].'" data-issn="'.$j['id'].'" data-pubdate="'.$j['date'].'"></span>';
  echo '<img class="getTOC '.$j['id'].'" src="img/lazyloader.gif" data-src="'.$j['img'].'">';
  /* preload $meta here; toggle when the TOC is fired into the Reveal window (see js) */
  echo (($meta && $lister->prefs->show_metainfo) ? '<div class="metaInfo">'.$meta.'</div>' : '');
  echo '<div class="getTOC title">';
  echo '<h5 title="'.$j['title'].'">'.$nbr_title.'</h5>';
  echo ($new_issues) ? '<span class="subheader fresh">['.__("last update") .' '. $wF . ']</span>' : '';
  echo '</div></div>';

}
?>
  </div>
  <!-- End Thumbnails -->

  <!-- Box für Toc-Inhalte, z.B. genutzt von Grid (s. js) -->
  <div id="tocModal" class="reveal-modal xlarge" data-reveal="">
    <div class="toc preloader"></div>
    <!-- This alert box will be switched on if something goes wrong (see conduit.js) -->
    <div data-alert="" id="tocAlertBox" class="alert-box warning invisible">
      <span id="tocAlertText"><?php echo __('Something seems to be wrong with the network') ?></span>
      <a class="button radius" href="checkout.php?action=contact&amp;lang=<?php echo $lister->prefs->current_lang ?>&amp;message=Feed%20error%20report&amp;body=Error%20report%20from%20JournalTouch%20for%20ISSN:%200000-0000"><i class="fi-mail"></i>&#160;<?php echo __('Please notify us!') ?></a>
    </div>
    <!-- This alert box will be switched on if no tocs are found (see conduit.js) -->
    <div data-alert="" id="tocNotFoundBox" class="alert-box info invisible">
      <span id="tocAlertText"><?php echo __('No table of contents found! Are you interested in this title?') ?></span>
      <a class="button radius" href="checkout.php?action=contact&amp;lang=<?php echo $lister->prefs->current_lang ?>&amp;message=The%20table%20of%20contents%20for%20this%20journal%20seems%20to%20be%20missing%20for%20ISSN:%200000-0000"><i class="fi-comment"></i> <?php echo __('Please notify us!') ?></a>
    </div>
    <a class="close-reveal-modal button radius alert">×</a>
  </div>
</div>


<footer>
  <div class="row">
    <div class="small-12 columns">
      <div class="large-6 columns">
        <p>
        <em>JournalTouch</em> © 2014 MPI Collective Goods Library, Bonn</p>
        <p><?php echo __('Tables of contents provided by <a href="http://www.crossref.org">CrossRef</a> and <a href="http://www.journaltocs.ac.uk/">JournalTocs</a>') ?></p>
      </div>
      <div class="large-6 columns">
        <ul class="inline-list right">
          <li><a class="button radius" href="checkout.php?action=contact&amp;lang=<?php echo $lister->prefs->current_lang ?>"><i class="fi-comment"></i> <?php echo __('Get in touch!') ?></a></li>
        </ul>
      </div>
    </div>
  </div>
</footer>


<!-- a fancy screensaver when screen is idle (see css for switching) -->
<?php if ($lister->prefs->show_screensaver) { ?>
<div id="screensaver" style="display:none">
  <div class="row">
    <div class="small-12 medium-12 large-12 columns left">
      <h1><?php echo __('Touch me!') ?></h1>
      <h2><?php echo __('What is this?') ?></h2>
      <p>
<?php
$lib_teaser = __("<em>JournalTouch</em> is the <strong>PLACEHOLDER's</strong> alerting service for newly published journal issues.");
$lib_teaser = str_replace('PLACEHOLDER', ' '.$lister->prefs->lib_name, $lib_teaser);
echo $lib_teaser;
?>
      </p>
      <p>
<?php echo __('It\'s easy &dash; select a journal and add interesting articles to your shopping basket. If there is an abstract
available, it will be indicated with an extra button.
When you are finished, click on your basket to check out.
You can now send the article information to your e-mail address, send a
request for the PDF files to the library, or view/save it as a
list. Export for citation management systems like Endnote is also available. <br/>
The list of journals is a selection of the journals licensed to the library.') ?>
      </p>
    </div>
  </div>
  <div class="row">
    <div class="small-8 medium-9 large-9 columns left">
      <p>
        <?php echo __('If a journal is missing, please let us know and we will add it to the list.') ?>
        <br />
        <br /><?php echo __('<strong><em>JournalTouch</em> is actively being developed by the library team.</strong>') ?>
        <br /><?php echo __('Tables of contents are provided by <strong>CrossRef</strong> and <strong>JournalTocs</strong>.') ?>
      </p>
    </div>
  </div>
  <div class="row"><h1><?php echo __('Touch the screen to get started...') ?></h1></div>
  <div class="row"><p class="text-center"><img src="img/bgcoll-logo.png"></img></p></div>
</div>
<?php } ?>
<!-- end screensaver -->


<script src="js/vendor/jquery.js"></script>
<script src="js/foundation.min.js"></script>
<script src="js/local/simpleCart.custom.js"></script>
<script src="js/vendor/jquery.unveil.min.js"></script>
<script src="js/vendor/waypoints.min.js"></script>
<script src="js/vendor/jquery.timeago.js"></script>
<script src="languages/<?php echo $lister->prefs->current_lang ?>/jquery.timeago.<?php echo $lister->prefs->current_lang ?>.js"></script>
<script src="js/vendor/tinysort.min.js"></script>
<script src="js/local/conduit.js"></script>
<script src="js/vendor/jquery.quicksearch.min.js"></script>

<script>
simpleCart({
  checkout: {
    type: "SendForm",
      url: "checkout.php",
      extra_data: {
        lang: "<?php echo $lister->prefs->current_lang ?>"
        }
    },
      cartColumns: [
{ view: function( item, column ) {
  var itemname, itemtitle;
  itemname = item.get('name');
  itemtitle = item.get('title');
  if (typeof itemtitle !== 'undefined' && itemtitle !== '') {
    return '<div class="item-name">'+itemtitle+' <i alt="we have citation data" class="fi-paperclip"></i></div>';
                } else {
                  return '<div class="item-name">'+itemname+'</div>';
                }
            },
              label: false
        },
  {
    view: "remove" ,
      text: "<?php echo __('remove') ?> <i class=\"fi-trash\"></i>" ,
      label: false
        }
    ]
});

simpleCart.bind( 'beforeRemove' , function(){
  if ($(".row-1").length == false) {
    $("#shelfIsEmpty").show();
    $('#checkOutButton, #emptyCartButton, #emptyConfirmButton').hide();
    // remove class to button (for CSS formatting)
    $('#myArticles').removeClass('full');
  } else {
    $('#checkOutButton, #emptyCartButton, #emptyConfirmButton').show();
    }
});

simpleCart.bind( 'afterAdd' , function(){
  // add class to button (for CSS formatting)
  $('#myArticles').addClass('full');
});

simpleCart.bind( 'load' , function(){
  if (simpleCart.quantity() > 0) {
    // add class to button (for CSS formatting)
    $('#myArticles').addClass('full');
   }
});

</script>
<script>
$(document).foundation();

var doc = document.documentElement;
doc.setAttribute('data-useragent', navigator.userAgent);
</script>

</body>
</html>

<?php
if ($lister->prefs->cache_main_enable) {
  file_put_contents($cachefile, ob_get_contents());
}
ob_end_flush();
?>
<!-- vim: set sw=2 ts=2 et ft=php fdm=marker: -->
