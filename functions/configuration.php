<?php
final class Config {
  private $data;
  private $path;

  private function get_including_file(): string|null {
      $backtrace = debug_backtrace();
      if (isset($backtrace[0]) && isset($backtrace[1])) {
          return $backtrace[1]['file']."@".$backtrace[1]['line'];
      } else {
          return null;
      }
  }

  public function __construct(){
    if (is_dir('config')) {
      $this->path='./config/config.json';
    } elseif (is_dir('../config')) {
      $this->path='../config/config.json';
    } else {
      if (is_dir('functions')) {
        mkdir('config');
      } elseif (is_dir('../functions')) {
        mkdir('../config');
      } else {
        die("configuration.php: could not find config folder");
      }
    }
    if (file_exists($this->path)) {
      $this->read();
    } else {
      $this->data=[];
      $this->write();
      error_log("configuration.php: could not find config file, creating new blank one");
    }
  }
  /**
   * @return true
   */
  private function write(): bool{
    $status=file_put_contents($this->path, json_encode($this->data, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
    if ($status) {
      return true;
    } else {
      die("configuration.php: could not write config file");
    }
  }
  /**
   * @return void
   */
  private function read() {
    $read = file_get_contents($this->path);
    if ($read) {
      $decode=json_decode($read,true);
      if ($decode !== null && json_last_error() === JSON_ERROR_NONE) {
        $this->data=$decode;
      } else {
        die("configuration.php: could not parse config file"); 
      }
    } else {
      die("configuration.php: could not read config file");
    }
  }
  public function exists(string $key): bool {
    return array_key_exists($key, $this->data);
  }
  public function get(string $key) {
    if (!$this->exists($key)) {
      trigger_error("configuration.php: get(): '{$key}'does not exist, called from '{$this->get_including_file()}'", E_USER_WARNING);
      return null;
    }
    return $this->data[$key];
  }
  public function set(string $key,array|string|false $value): void {
    $this->data[$key]=$value;
    $this->write();
  }
  public function setall(array $vals): void {
    $this->data=array_replace($this->data, $vals);
    $this->write();
  }
  public function delete(string $key) : bool {
    if (!$this->exists($key)) {
      return false;
    } else {
      unset($this->data[$key]);
      $this->write();
      return true;
    }
  }

}

?>