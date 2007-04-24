<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @package    symfony.runtime.addon
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */

/**
 *
 * This is taken from Harry Fueck's Thumbnail class and 
 * converted for PHP5 strict compliance for use with symfony.
 *
 * @package    symfony.runtime.addon
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
class sfThumbnail
{
  /**
  * Maximum width of the thumbnail in pixels
  * @access private
  * @var int
  */
  private $maxWidth;

  /**
  * Maximum height of the thumbnail in pixels
  * @access private
  * @var int
  */
  private $maxHeight;

  /**
  * Whether to scale image to fit thumbnail (true) or
  * strech to fit (false)
  * @access private
  * @var boolean
  */
  private $scale;

  /**
  * Whether to inflate images smaller the the thumbnail
  * @access private
  * @var boolean
  */
  private $inflate;

  /**
  * List of accepted image types based on MIME description
  * @access private
  * @var array
  */
  private $imgTypes;

  /**
  * Stores function names for each image type e.g. imagecreatefromjpeg
  * @access private
  * @var array
  */
  private $imgLoaders;

  /**
  * Stores function names for each image type e.g. imagejpeg
  * @access private
  * @var array
  */
  private $imgCreators;

  /**
  * The source image
  * @access private
  * @var resource
  */
  private $source;

  /**
  * Width of source image in pixels
  * @access private
  * @var int
  */
  private $sourceWidth;

  /**
  * Height of source image in pixels
  * @access private
  * @var int
  */
  private $sourceHeight;

  /**
  * MIME type of source image
  * @access private
  * @var string
  */
  private $sourceMime;

  /**
  * The thumbnail
  * @access private
  * @var resource
  */
  private $thumb;

  /**
  * Width of thumbnail in pixels
  * @access private
  * @var int
  */
  private $thumbWidth;

  /**
  * Height of thumbnail in pixels
  * @access private
  * @var int
  */
  private $thumbHeight;

  /**
  * Image data from call to GetImageSize needed for saveThumb
  * @access private
  * @var resource
  */
  private $imgData;

  /**
   * JPEG output quality
   * @access private
   * @var int
   */
  private $quality;

  /**
  * Thumbnail constructor
  *
  * @param int (optional) max width of thumbnail
  * @param int (optional) max height of thumbnail
  * @param boolean (optional) if true image scales
  * @param boolean (optional) if true inflate small images
  */
  public function __construct($maxWidth = null, $maxHeight = null, $scale = true, $inflate = true, $quality = 75)
  {
    $this->maxWidth  = $maxWidth;
    $this->maxHeight = $maxHeight;
    $this->scale     = $scale;
    $this->inflate   = $inflate;
    $this->quality   = $quality;

    $this->imgTypes = array('image/jpeg', 'image/png', 'image/gif');
    $this->imgLoaders = array(
      'image/jpeg' => 'imagecreatefromjpeg',
      'image/png'  => 'imagecreatefrompng',
      'image/gif'  => 'imagecreatefromgif',
    );

    $this->imgCreators = array(
      'image/jpeg' => 'imagejpeg',
      'image/png'  => 'imagepng',
      'image/gif'  => 'imagegif',
    );
  }

  /**
  * Loads an image from a file and creates an internal thumbnail out of it
  *
  * @param string filename (with absolute path) of the image to load
  *
  * @return boolean True if the image was properly loaded
  * @access public
  * @throws Exception If the GD extension is not available, if the image cannot be loaded, or if its mime type is not supported
  */
  public function loadFile($image)
  {
    $imgData = @GetImageSize($image);

    if (!$imgData)
    {
      throw new Exception(sprintf('Could not load image %s', $image));
    }

    if (in_array($imgData['mime'], $this->imgTypes))
    {
      $loader = $this->imgLoaders[$imgData['mime']];
      if(!function_exists($loader))
      {
        throw new Exception(sprintf('Function %s not available. Please enable the GD extension.', $loader));
      }
      
      $this->source = $loader($image);      
      $this->sourceWidth = $imgData[0];
      $this->sourceHeight = $imgData[1];
      $this->sourceMime = $imgData['mime'];
      $this->imgData = $imgData;
      $this->initThumb();

      return true;
    }
    else
    {
      throw new Exception(sprintf('Image MIME type %s not supported', $imgData['mime']));
    }
  }

  /**
  * Loads an image from a string (e.g. database) and creates an internal thumbnail out of it
  *
  * @param string the image string (must be a format accepted by imagecreatefromstring())
  * @param string mime type of the image
  *
  * @return boolean True if the image was properly loaded
  * @access public
  * @throws Exception If image mime type is not supported
  */
  public function loadData($image, $mime)
  {
    if (in_array($mime,$this->imgTypes))
    {
      $this->source=imagecreatefromstring($image);
      $this->sourceWidth=imagesx($this->source);
      $this->sourceHeight=imagesy($this->source);
      $this->sourceMime=$mime;
      $this->initThumb();

      return true;
    }
    else
    {
      throw new Exception(sprintf('Image MIME type %s not supported', $mime));
    }
  }

  /**
  * Returns the mime type for the thumbnail
  * @return string
  * @access public
  */
  public function getMime()
  {
    return $this->sourceMime;
  }

  /**
  * Returns the width of the thumbnail
  * @return int
  * @access public
  */
  public function getThumbWidth()
  {
    return $this->thumbWidth;
  }

  /**
  * Returns the height of the thumbnail
  * @return int
  * @access public
  */
  public function getThumbHeight()
  {
    return $this->thumbHeight;
  }

  /**
  * Creates the thumbnail image from the source and stores it in the object
  *
  * @return void
  * @access private
  */
  private function initThumb()
  {
    if ($this->maxWidth > 0)
    {
      $ratioWidth = $this->maxWidth / $this->sourceWidth;
    }
    if ($this->maxHeight > 0)
    {
      $ratioHeight = $this->maxHeight / $this->sourceHeight;
    }

    if ($this->scale)
    {
      if ($this->maxWidth && $this->maxHeight)
      {
        $ratio = ($ratioWidth < $ratioHeight) ? $ratioWidth : $ratioHeight;
      }
      if ($this->maxWidth xor $this->maxHeight)
      {
        $ratio = (isset($ratioWidth)) ? $ratioWidth : $ratioHeight;
      }
      if ((!$this->maxWidth && !$this->maxHeight) || (!$this->inflate && $ratio > 1))
      {
        $ratio = 1;
      }

      $this->thumbWidth = floor($ratio * $this->sourceWidth);
      $this->thumbHeight = floor($ratio * $this->sourceHeight);
    }
    else
    {
      if (!$ratioWidth || (!$this->inflate && $ratioWidth > 1))
      {
        $ratioWidth = 1;
      }
      if (!$ratioHeight || (!$this->inflate && $ratioHeight > 1))
      {
        $ratioHeight = 1;
      }
      $this->thumbWidth = floor($ratioWidth * $this->sourceWidth);
      $this->thumbHeight = floor($ratioHeight * $this->sourceHeight);
    }

    $this->thumb = imagecreatetruecolor($this->thumbWidth, $this->thumbHeight);

    if ($this->sourceWidth == $this->maxWidth && $this->sourceHeight == $this->maxHeight)
    {
      $this->thumb = $this->source;
    }
    else
    {
      imagecopyresampled( $this->thumb, $this->source, 0, 0, 0, 0, $this->thumbWidth, $this->thumbHeight, $this->sourceWidth, $this->sourceHeight);
    }
  }

  /**
  * Saves the thumbnail to the filesystem
  * If no target mime type is specified, the thumbnail is created with the same mime type as the source file.
  *
  * @param string the image thumbnail file destination (with absolute path)
  * @param string The mime-type of the thumbnail (possible values are 'image/jpeg', 'image/png', and 'image/gif')
  *
  * @access public 
  * @return void
  */
  public function save($thumbDest, $targetMime = null)
  {
    if($targetMime !== null)
    {
      $creator = $this->imgCreators[$targetMime];
    }
    else
    {
      $creator = $this->imgCreators[$this->sourceMime];
    }
    
    if ($creator == 'imagejpeg')
    {
      imagejpeg($this->thumb, $thumbDest, $this->quality);
    }
    else
    {
      $creator($this->thumb, $thumbDest);
    }
  }

  public function freeSource()
  {
    if (is_resource($this->source))
    {
      imagedestroy($this->source);
    }
  }

  public function freeThumb()
  {
    if (is_resource($this->thumb))
    {
      imagedestroy($this->thumb);
    }
  }

  public function freeAll()
  {
    $this->freeSource();
    $this->freeThumb();
  }

  public function __destruct()
  {
    $this->freeAll();
  }
}
