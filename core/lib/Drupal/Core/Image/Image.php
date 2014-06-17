<?php

/**
 * @file
 * Contains \Drupal\Core\Image\Image.
 */

namespace Drupal\Core\Image;

use Drupal\Core\ImageToolkit\ImageToolkitInterface;

/**
 * Defines an image object to represent an image file.
 *
 * @see \Drupal\Core\ImageToolkit\ImageToolkitInterface
 * @see \Drupal\image\ImageEffectInterface
 *
 * @ingroup image
 */
class Image implements ImageInterface {

  /**
   * Path of the image file.
   *
   * @var string
   */
  protected $source = '';

  /**
   * An image toolkit object.
   *
   * @var \Drupal\Core\ImageToolkit\ImageToolkitInterface
   */
  protected $toolkit;

  /**
   * File size in bytes.
   *
   * @var int
   */
  protected $fileSize;

  /**
   * If this image object is valid.
   *
   * @var bool
   */
  protected $valid = FALSE;

  /**
   * Constructs a new Image object.
   *
   * @param \Drupal\Core\ImageToolkit\ImageToolkitInterface $toolkit
   *   The image toolkit.
   * @param string|null $source
   *   (optional) The path to an image file, or NULL to construct the object
   *   with no image source.
   */
  public function __construct(ImageToolkitInterface $toolkit, $source = NULL) {
    $this->toolkit = $toolkit;
    if ($source) {
      $this->source = $source;
      $this->parseFile();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isValid() {
    return $this->valid;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeight() {
    return $this->toolkit->getHeight($this);
  }

  /**
   * {@inheritdoc}
   */
  public function getWidth() {
    return $this->toolkit->getWidth($this);
  }

  /**
   * {@inheritdoc}
   */
  public function getFileSize() {
    return $this->fileSize;
  }

  /**
   * {@inheritdoc}
   */
  public function getMimeType() {
    return $this->toolkit->getMimeType($this);
  }

  /**
   * {@inheritdoc}
   */
  public function getSource() {
    return $this->source;
  }

  /**
   * {@inheritdoc}
   */
  public function getToolkitId() {
    return $this->toolkit->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getToolkit() {
    return $this->toolkit;
  }

  /**
   * {@inheritdoc}
   */
  public function save($destination = NULL) {
    // Return immediately if the image is not valid.
    if (!$this->isValid()) {
      return FALSE;
    }

    $destination = $destination ?: $this->getSource();
    if ($return = $this->toolkit->save($this, $destination)) {
      // Clear the cached file size and refresh the image information.
      clearstatcache(TRUE, $destination);
      $this->fileSize = filesize($destination);
      $this->source = $destination;

      // @todo Use File utility when https://drupal.org/node/2050759 is in.
      if ($this->chmod($destination)) {
        return $return;
      }
    }
    return FALSE;
  }

  /**
   * Determines if a file contains a valid image.
   *
   * Drupal supports GIF, JPG and PNG file formats when used with the GD
   * toolkit, and may support others, depending on which toolkits are
   * installed.
   *
   * @return bool
   *   FALSE, if the file could not be found or is not an image. Otherwise, the
   *   image information is populated.
   */
  protected function parseFile() {
    if ($this->valid = $this->toolkit->parseFile($this)) {
      $this->fileSize = filesize($this->source);
    }
    return $this->valid;
  }

  /**
   * Passes through calls that represent image toolkit operations onto the
   * image toolkit.
   *
   * This is a temporary solution to keep patches reviewable. The __call()
   * method will be replaced in https://drupal.org/node/2110499 with a new
   * interface method ImageInterface::apply(). An image operation will be
   * performed as in the next example:
   * @code
   * $image = new Image($toolkit, $path);
   * $image->apply('scale', array('width' => 50, 'height' => 100));
   * @endcode
   * Also in https://drupal.org/node/2110499 operation arguments sent to toolkit
   * will be moved to a keyed array, unifying the interface of toolkit
   * operations.
   *
   * @todo Drop this in https://drupal.org/node/2110499 in favor of new apply().
   */
  public function __call($method, $arguments) {
    // @todo Temporary to avoid that legacy GD setResource(), getResource(),
    //  hasResource() methods moved to GD toolkit in #2103621, setWidth(),
    //  setHeight() methods moved to ImageToolkitInterface in #2196067,
    //  getType() method moved to GDToolkit in #2211227 get
    //  invoked from this class anyway through the magic __call. Will be
    //  removed through https://drupal.org/node/2073759, when
    //  call_user_func_array() will be replaced by
    //  $this->toolkit->apply($name, $this, $arguments).
    if (in_array($method, array('setResource', 'getResource', 'hasResource', 'setWidth', 'setHeight', 'getType'))) {
      throw new \BadMethodCallException($method);
    }
    if (is_callable(array($this->toolkit, $method))) {
      // @todo In https://drupal.org/node/2073759, call_user_func_array() will
      //   be replaced by $this->toolkit->apply($name, $this, $arguments).
      array_unshift($arguments, $this);
      return call_user_func_array(array($this->toolkit, $method), $arguments);
    }
    throw new \BadMethodCallException($method);
  }

  /**
   * Provides a wrapper for drupal_chmod() to allow unit testing.
   *
   * @param string $uri
   *   A string containing a URI file, or directory path.
   * @param int $mode
   *   Integer value for the permissions. Consult PHP chmod() documentation for
   *   more information.
   *
   * @see drupal_chmod()
   *
   * @todo Remove when https://drupal.org/node/2050759 is in.
   *
   * @return bool
   *   TRUE for success, FALSE in the event of an error.
   */
  protected function chmod($uri, $mode = NULL) {
    return drupal_chmod($uri, $mode);
  }

}
