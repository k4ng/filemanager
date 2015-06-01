<?php
	error_reporting(0);
	/********************************
	Simple PHP File Manager
	Copyright John Campbell (jcampbell1) - jcampbell1@gmail.com
	https://github.com/jcampbell1

	Liscense: MIT
	
	Modify By : Kang Cahya
	********************************/

	setlocale(LC_ALL,'en_US.UTF-8');

	$tmp = realpath($_REQUEST['file']);
	if($tmp === false)
		err(404,'File or Directory Not Found');
	if(substr($tmp, 0,strlen(__DIR__)) !== __DIR__)
		err(403,"Forbidden");

	if(!$_COOKIE['_sfm_xsrf'])
		setcookie('_sfm_xsrf',bin2hex(openssl_random_pseudo_bytes(16)));
	if($_POST) {
		if($_COOKIE['_sfm_xsrf'] !== $_POST['xsrf'] || !$_POST['xsrf'])
			err(403,"XSRF Failure");
	}

	$file = $_REQUEST['file'] ?: '.';
	if($_GET['do'] == 'list') {
		if (is_dir($file)) {
			$directory = $file;
			$result = array();
			$files = array_diff(scandir($directory), array('.','..'));
			foreach($files as $entry) if($entry !== basename(__FILE__)) {
				$i = $directory . '/' . $entry;
				$stat = stat($i);
				$result[] = array(
					'mtime' => $stat['mtime'],
					'size' => $stat['size'],
					'name' => basename($i),
					'path' => preg_replace('@^\./@', '', $i),
					'is_dir' => is_dir($i),
					'is_deleteable' => (!is_dir($i) && is_writable($directory)) || 
									   (is_dir($i) && is_writable($directory) && is_recursively_deleteable($i)),
					'is_readable' => is_readable($i),
					'is_writable' => is_writable($i),
					'is_executable' => is_executable($i),
				);
			}
		} else {
			err(412,"Not a Directory");
		}
		echo json_encode(array('success' => true, 'is_writable' => is_writable($file), 'results' =>$result));
		exit;
	} elseif ($_POST['do'] == 'delete') {
		rmrf($file);
		exit;
	} elseif ($_POST['do'] == 'mkdir') {
		chdir($file);
		@mkdir($_POST['name']);
		exit;
	} elseif ($_POST['do'] == 'upload') {
		var_dump($_POST);
		var_dump($_FILES);
		var_dump($_FILES['file_data']['tmp_name']);
		var_dump(move_uploaded_file($_FILES['file_data']['tmp_name'], $file.'/'.$_FILES['file_data']['name']));
		exit;
	} elseif ($_GET['do'] == 'download') {
		$filename = basename($file);
		header('Content-Type: ' . mime_content_type($file));
		header('Content-Length: '. filesize($file));
		header(sprintf('Content-Disposition: attachment; filename=%s',
			strpos('MSIE',$_SERVER['HTTP_REFERER']) ? rawurlencode($filename) : "\"$filename\"" ));
		ob_flush();
		readfile($file);
		exit;
	}
	function rmrf($dir) {
		if(is_dir($dir)) {
			$files = array_diff(scandir($dir), array('.','..'));
			foreach ($files as $file)
				rmrf("$dir/$file");
			rmdir($dir);
		} else {
			unlink($dir);
		}
	}
	function is_recursively_deleteable($d) {
		$stack = array($d);
		while($dir = array_pop($stack)) {
			if(!is_readable($dir) || !is_writable($dir)) 
				return false;
			$files = array_diff(scandir($dir), array('.','..'));
			foreach($files as $file) if(is_dir($file)) {
				$stack[] = "$dir/$file";
			}
		}
		return true;
	}

	function err($code,$msg) {
		echo json_encode(array('error' => array('code'=>intval($code), 'msg' => $msg)));
		exit;
	}

	function asBytes($ini_v) {
		$ini_v = trim($ini_v);
		$s = array('g'=> 1<<30, 'm' => 1<<20, 'k' => 1<<10);
		return intval($ini_v) * ($s[strtolower(substr($ini_v,-1))] ?: 1);
	}
	$MAX_UPLOAD_SIZE = min(asBytes(ini_get('post_max_size')), asBytes(ini_get('upload_max_filesize')));
?>
<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">
		<link rel="stylesheet" href="https://raw.githubusercontent.com/markusslima/bootstrap-filestyle/master/src/bootstrap-filestyle.js">
		<style>
			.is_dir .size {color:transparent;font-size:0;}
			.is_dir .size:before {content: "--"; font-size:14px;color:#333;}
			.is_dir .download{visibility: hidden}
			a.delete {
				background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAMAAAAoLQ9TAAAAA3NCSVQICAjb4U/gAAAACXBIWXMAAABvAAAAbwHxotxDAAAAGXRFWHRTb2Z0d2FyZQB3d3cuaW5rc2NhcGUub3Jnm+48GgAAADNQTFRF////AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA8YBMDAAAABB0Uk5TAAECJCdufoaxsrTD0OTm9Wmrmp4AAABgSURBVBhXZY9LDsAgCEQfir9alfuftgtN04a3GwLDDAAxtzlbjmwkLRu1DltJAOSyrgDa7RIgWQl7NRRLEFc/GkJfkWzKi1qmDT6MxqxwH6BOP3AnztS9dcF8dFeOf/0Hlg4E3YAxGoUAAAAASUVORK5CYII23d7e476e74f68d49cbddbba1bcae1d7) no-repeat scroll 0px 5px;
				padding:4px 0 4px 20px;
				margin-left:15px;
				color:#FF0000;
				font-weight:bold;
			}
			.name {
				background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAAtQAAALUBOdDOnwAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAAElSURBVEiJ7ZYtSwRRFIafVwSLiBaLwbRFcWERDGoQq/9gNfhXDEajv0EEk80tChZRBBGTFoMmtygiguE17IiXgZ09d5m4Lxzm6/A8w7kwc7HNXwFtoAs4oz6A5ZSTlgowAJIegA5wSiwLwCHwBqzbfiw3jJeuJ4F72xcRuqTv4vQK6Ehas/2a9owF33RQdoAn4EzSTJ2Cn+J4CcwDi8Bx2lAeUVZs30raBmaLWyvAakggaQtoBF0ntl8k7YYFwBQwHRRM9HvQV2D7KAivTNWIWvQWLpJz2+9ZAmATWAoKnoG7LIHtgyC8MqMRDUzViBrAXJBzbfsrS0Dv39AKCvaBmyyB7b0gvDJ1fa5HguFTXuRPoClpY0hes2D8p4ZtS1pdoJ0yfwFqmpYrmLMz6wAAAABJRU5ErkJggg17f743e2f7daab8486becdd50048ccbe) no-repeat scroll 0px 12px;
				padding:15px 0 10px 30px;
				color:#000000;
				font-weight:bold;
			}
			.is_dir .name {
				background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAABbAAAAWwB+66crQAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAAFGSURBVEiJ7da/ipNBFAXw373zWShutzY2/tlKsNBKKx/ANxBsFnu7BUEQC0vtbewUC0EQS9/ALQQrQbCxERRELTTZ7HctkkBcMVkh6XLgNMOZc+bAMHeiqqwSeXAhorsa0e1GtJ8R7deE7yPa3Yj4S78IMdsgIs6Qb/EFL2Zkl6lLeFK1f/1/ArqJ8Um6c7Q71BH6K1X18c9m7R5uR+Qx2kMM51uP3lXVJ+QOOSKLHNBuVJWDRJIPyH6iXcQ98maQn6k3tPuMdqvq67xzRcQm3YVp+3+jblFbHTbxjTpFno1o8/dJ9As0sU//gzgR4zorw2Ba8wP9NXxfkvFR8hHOTwNeVtXrJZmDiPYKFxODZRrPYHr1Vot1wDpgHTAOGGJjBd4b2OvwHNsRLSzvsTuObTyD0+Tj8TQ71KQ6DIfkU2zFqr8tvwG3Sn8bZSDfTgAAAABJRU5ErkJgggf35d9d081376bb47dd16ff19c28ab5bf) no-repeat scroll 0px 10px;
				padding:15px 0 10px 30px;
				color:#000000;
				font-weight:bold;
			}
			.download {
				background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAHYwAAB2MB6j17PwAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAADHSURBVDiNzZIxCsJAEEXfDynEUoJptBNMJ3qSFDmBF9BCPIOHyCVyglzBG4g3sBCEFK7NiqvZDUEbP3zYXf78mZ0ZjDH4COwBY3kI6SLCGDrnQUjUZdALf2ggaSIpBzLneSlpK2necvB0fwyceU3gyRMwaukDI1wAVyf4Bqy82o49yIG7NViHdLJiLyTtgKkxZtPVyBI4AkUoi6e6wsaUMTCzf066snwgsTGXn/cgds6ppCyofEfqXmraM+/LOgIqoPmi+gaoHvJZzAVA64KqAAAAAElFTkSuQmCC767a946ae2237285119eef7b5bda15f9) no-repeat scroll 0px 5px;
				padding:4px 0 4px 20px;
				color:#000000;
				font-weight:bold;
			}
		</style>

		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
		<script>		
		//filemanager
		(function($){
			$.fn.tablesorter = function() {
				var $table = this;
				this.find('th').click(function() {
					var idx = $(this).index();
					var direction = $(this).hasClass('sort_asc');
					$table.tablesortby(idx,direction);
				});
				return this;
			};
			$.fn.tablesortby = function(idx,direction) {
				var $rows = this.find('tbody tr');
				function elementToVal(a) {
					var $a_elem = $(a).find('td:nth-child('+(idx+1)+')');
					var a_val = $a_elem.attr('data-sort') || $a_elem.text();
					return (a_val == parseInt(a_val) ? parseInt(a_val) : a_val);
				}
				$rows.sort(function(a,b){
					var a_val = elementToVal(a), b_val = elementToVal(b);
					return (a_val > b_val ? 1 : (a_val == b_val ? 0 : -1)) * (direction ? 1 : -1);
				})
				this.find('th').removeClass('sort_asc sort_desc');
				$(this).find('thead th:nth-child('+(idx+1)+')').addClass(direction ? 'sort_desc' : 'sort_asc');
				for(var i =0;i<$rows.length;i++)
					this.append($rows[i]);
				this.settablesortmarkers();
				return this;
			}
			$.fn.retablesort = function() {
				var $e = this.find('thead th.sort_asc, thead th.sort_desc');
				if($e.length)
					this.tablesortby($e.index(), $e.hasClass('sort_desc') );
				
				return this;
			}
			$.fn.settablesortmarkers = function() {
				this.find('thead th span.indicator').remove();
				this.find('thead th.sort_asc').append('<span class="indicator">&darr;<span>');
				this.find('thead th.sort_desc').append('<span class="indicator">&uarr;<span>');
				return this;
			}
		})(jQuery);
		$(function(){
			var XSRF = (document.cookie.match('(^|; )_sfm_xsrf=([^;]*)')||0)[2];
			var MAX_UPLOAD_SIZE = <?php echo $MAX_UPLOAD_SIZE ?>;
			var $tbody = $('#list');
			$(window).bind('hashchange',list).trigger('hashchange');
			$('#table').tablesorter();
			
			$('.delete').live('click',function(data) {
				$.post("",{'do':'delete',file:$(this).attr('data-file'),xsrf:XSRF},function(response){
					list();
				},'json');
				return false;
			});

			$('#mkdir').submit(function(e) {
				var hashval = window.location.hash.substr(1),
					$dir = $(this).find('[name=name]');
				e.preventDefault();
				$dir.val().length && $.post('?',{'do':'mkdir',name:$dir.val(),xsrf:XSRF,file:hashval},function(data){
					list();
				},'json');
				$dir.val('');
				return false;
			});

			// file upload stuff
			$('#file_drop_target').bind('dragover',function(){
				$(this).addClass('drag_over');
				return false;
			}).bind('dragend',function(){
				$(this).removeClass('drag_over');
				return false;
			}).bind('drop',function(e){
				e.preventDefault();
				var files = e.originalEvent.dataTransfer.files;
				$.each(files,function(k,file) {
					uploadFile(file);
				});
				$(this).removeClass('drag_over');
			});
			$('input[type=file]').change(function(e) {
				e.preventDefault();
				$.each(this.files,function(k,file) {
					uploadFile(file);
				});
			});


			function uploadFile(file) {
				var folder = window.location.hash.substr(1);

				if(file.size > MAX_UPLOAD_SIZE) {
					var $error_row = renderFileSizeErrorRow(file,folder);
					$('#upload_progress').append($error_row);
					window.setTimeout(function(){$error_row.fadeOut();},5000);
					return false;
				}
				
				var $row = renderFileUploadRow(file,folder);
				$('#upload_progress').append($row);
				var fd = new FormData();
				fd.append('file_data',file);
				fd.append('file',folder);
				fd.append('xsrf',XSRF);
				fd.append('do','upload');
				var xhr = new XMLHttpRequest();
				xhr.open('POST', '?');
				xhr.onload = function() {
					$row.remove();
					list();
				};
				xhr.upload.onprogress = function(e){
					if(e.lengthComputable) {
						$row.find('.progress').css('width',(e.loaded/e.total*100 | 0)+'%' );
					}
				};
				xhr.send(fd);
			}
			function renderFileUploadRow(file,folder) {
				return $row = $('<div/>')
					.append( $('<span class="fileuploadname" />').text( (folder ? folder+'/':'')+file.name))
					.append( $('<div class="progress_track"><div class="progress"></div></div>')  )
					.append( $('<span class="size" />').text(formatFileSize(file.size)) )
			};
			function renderFileSizeErrorRow(file,folder) {
				return $row = $('<div class="error" />')
					.append( $('<span class="fileuploadname" />').text( 'Error: ' + (folder ? folder+'/':'')+file.name))
					.append( $('<span/>').html(' file size - <b>' + formatFileSize(file.size) + '</b>'
						+' exceeds max upload size of <b>' + formatFileSize(MAX_UPLOAD_SIZE) + '</b>')  );
			}

			function list() {
				var hashval = window.location.hash.substr(1);
				$.get('?',{'do':'list','file':hashval},function(data) {
					$tbody.empty();
					$('#breadcrumb').empty().html(renderBreadcrumbs(hashval));
					if(data.success) {
						$.each(data.results,function(k,v){
							$tbody.append(renderFileRow(v));
						});
						!data.results.length && $tbody.append('<tr><td class="empty" colspan=5>This folder is empty</td</td>')
						data.is_writable ? $('body').removeClass('no_write') : $('body').addClass('no_write');
					} else {
						console.warn(data.error.msg);
					}
					$('#table').retablesort();
				},'json');
			}
			function renderFileRow(data) {
				var $link = $('<a class="name" />')
					.attr('href', data.is_dir ? '#' + data.path : './'+data.path)
					.text(data.name);
				var $dl_link = $('<a/>').attr('href','?do=download&file='+encodeURIComponent(data.path))
					.addClass('download').text('download');
				var $delete_link = $('<a href="#" />').attr('data-file',data.path).addClass('delete').text('delete');
				var perms = [];
				if(data.is_readable) perms.push('read');
				if(data.is_writable) perms.push('write');
				if(data.is_executable) perms.push('exec');
				var $html = $('<tr />')
					.addClass(data.is_dir ? 'is_dir' : '')
					.append( $('<td class="first" />').append($link) )
					.append( $('<td/>').attr('data-sort',data.is_dir ? -1 : data.size)
						.html($('<span class="size" />').text(formatFileSize(data.size))) ) 
					.append( $('<td/>').attr('data-sort',data.mtime).text(formatTimestamp(data.mtime)) )
					.append( $('<td/>').text(perms.join('+')) )
					.append( $('<td/>').append($dl_link).append( data.is_deleteable ? $delete_link : '') )
				return $html;
			}
			function renderBreadcrumbs(path) {
				var base = "",
					$html = $('<div/>').append( $('<a href=#>Home</a></div>') );
				$.each(path.split('/'),function(k,v){
					if(v) {
						$html.append( $('<span/>').text(' ▸ ') )
							.append( $('<a/>').attr('href','#'+base+v).text(v) );
						base += v + '/';
					}
				});
				return $html;
			}
			function formatTimestamp(unix_timestamp) {
				var m = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
				var d = new Date(unix_timestamp*1000);
				return [m[d.getMonth()],' ',d.getDate(),', ',d.getFullYear()," ",
					(d.getHours() % 12 || 12),":",(d.getMinutes() < 10 ? '0' : '')+d.getMinutes(),
					" ",d.getHours() >= 12 ? 'PM' : 'AM'].join('');
			}
			function formatFileSize(bytes) {
				var s = ['bytes', 'KB','MB','GB','TB','PB','EB'];
				for(var pos = 0;bytes >= 1000; pos++,bytes /= 1024);
				var d = Math.round(bytes*10);
				return pos ? [parseInt(d/10),".",d%10," ",s[pos]].join('') : bytes + ' bytes';
			}
		})

		</script>
	</head>
	<body>
		<nav class="navbar navbar-inverse navbar-fixed-top">
			<div class="container">
				<!-- Brand and toggle get grouped for better mobile display -->
				<div class="navbar-header">
					<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
						<span class="sr-only">Toggle navigation</span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
					</button>
					<a class="navbar-brand" href="#">Filemanager</a>
				</div>

				<!-- Collect the nav links, forms, and other content for toggling -->
				<div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
					<div id="file_drop_target" class="navbar-form navbar-left">
						<input type="file" class="btn btn-default" multiple />
					</div>
					<form class="navbar-form navbar-right" action="?" method="post" id="mkdir">
						<div class="input-group">
							<input type="text" class="form-control" placeholder="New Folder" id="dirname" type="text" name="name">
							<span class="input-group-btn">
								<input type="submit" value="Create" class="btn btn-primary" />
							</span>
						</div>
					</form>
				</div><!-- /.navbar-collapse -->
			</div><!-- /.container-fluid -->
		</nav>
		<div class="container" style="margin-top:60px;">
			<br />
			<ol class="breadcrumb">
			  <b>Location : </b>
			  <li id="breadcrumb">&nbsp;</li>
			</ol>

			<div class="text-danger" id="upload_progress"></div>
			<table id="table" class="table">
				<thead>
					<tr>
						<th>Name</th>
						<th>Size</th>
						<th>Modified</th>
						<th>Permissions</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody id="list">
				</tbody>
			</table>
		</div>
	</body>
</html>
