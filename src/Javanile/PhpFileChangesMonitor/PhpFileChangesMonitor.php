<?php

##
class PhpFileChangesMonitor {

	## folder under monitor
	private $dir;

	## snapshot file
	private $snp;

	## hooks for callback
	private $hooks = array();

	## construct
	public function __construct($dir,$snp=NULL) {
		$this->dir = $dir;
		$this->snp = $snp ? $snp : $dir.'/.snp';
	}

	## run monitor session to found folder changes
	public function exec() {

		## get current (latest) folder snapshot to compare with stored
		$s1 = trim(static::snapshot($this->dir));
		$s1 = $s1 ? explode("\n",$s1) : array();

		## get stored (old) folder snapshot to compare with latest
		$s0 = trim(@file_get_contents($this->snp));
		$s0 = $s0 ? explode("\n",$s0) : array();

		## direct diff found real-new items
		$d1 = array_diff($s1,$s0);

		## reverse diff found updated or deleted items
		$d0 = array_diff($s0,$s1);

		## preview
		echo 's1: '.count($s1)."\n";
		/*
		foreach($s1 as $l) {
			echo ' - '.$l."\n";
		}
		*/
		echo 's0: '.count($s0)."\n";
		/*
		foreach($s0 as $l) {
			echo ' - '.$l."\n";
		}
		*/
		echo 'd1: '.count($d1)."\n";
		echo 'd0: '.count($d0)."\n";

		##
		$new = array();

		##
		foreach($d1 as $l) {
			list($t,$n,$d) = explode('|',$l,3);
			$new[$n] = $l;
		}

		##
		foreach($d0 as $i=>$l) {
			list($t,$n,$d) = explode('|',$l,3);

			if (isset($new[$n])) {
				echo ' - update: ['.$d.'] '.$n."\n";
				if (static::callback('update',array($n,$d=='d'))) {
					$s0[$i] = $new[$n];
					echo ' - (done)'."\n\n";
				} else {
					echo ' - (fail)'."\n\n";
				}
				unset($new[$n]);
			} else {
				echo ' - delete: ['.$d.'] '.$n."\n";
				if (static::callback('delete',array($n,$d=='d'))) {
					unset($s0[$i]);
					echo '   (done)'."\n\n";
				} else {
					echo '   (fail)'."\n\n";
				}
			}
		}

		##
		foreach($new as $i=>$l) {
			list($t,$n,$d) = explode('|',$l,3);

			echo ' - create: ['.$d.'] '.$n."\n";
			if (static::callback('create',array($n,$d=='d'))) {
				$s0[] = $l;
				echo '   (done)'."\n\n";
			} else {
				echo '   (fail)'."\n\n";
			}
		}

		## store updated version of folder snapshot
		@file_put_contents($this->snp,trim(implode("\n",$s0)));
	}

	##
	public function hook($hook,$callback) {
		$this->hooks[$hook][] = $callback;
	}

	##
	private function callback($hook,$params) {

		##
		$done = true;

		##
		if (isset($this->hooks[$hook]) && count($this->hooks[$hook])>0) {
			foreach($this->hooks[$hook] as $callback) {
				if (function_exists($callback)) {
					$done = call_user_func_array($callback,$params) ? $done : false;
				}
			}
		}

		##
		return $done;
	}

	##
	private static function snapshot($dir) {

		## sanitize
		$dir = rtrim($dir,'/');

		##
		$d = scandir($dir);
		$o = "";

		##
		foreach($d as $f) {
			if ($f[0] != '.') {
				$f = $dir.'/'.$f;
				if (is_dir($f)) {
					$o.= filemtime($f).'|'.$f.'|d'."\n";
					$o.= static::snapshot($f);
				} else {
					$o.= filemtime($f).'|'.$f.'|f'."\n";
				}
			}
		}

		##
		return $o;
	}
}



