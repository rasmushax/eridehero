#!/bin/bash

  # Plugin name
  PLUGIN_NAME="housefresh-tools"
  BUILD_DIR="build"
  RELEASE_DIR="$BUILD_DIR/$PLUGIN_NAME"

  # Clean previous build
  rm -rf $BUILD_DIR
  mkdir -p $RELEASE_DIR

  # Copy plugin files
  cp -r * $RELEASE_DIR/ 2>/dev/null || :

  # Remove development files
  rm -rf $RELEASE_DIR/.git
  rm -rf $RELEASE_DIR/.gitignore
  rm -rf $RELEASE_DIR/.gitattributes
  rm -rf $RELEASE_DIR/composer.json
  rm -rf $RELEASE_DIR/composer.lock
  rm -rf $RELEASE_DIR/tests
  rm -rf $RELEASE_DIR/phpunit.xml
  rm -rf $RELEASE_DIR/.phpunit.result.cache
  rm -rf $RELEASE_DIR/node_modules
  rm -rf $RELEASE_DIR/package.json
  rm -rf $RELEASE_DIR/package-lock.json
  rm -rf $RELEASE_DIR/build
  rm -rf $RELEASE_DIR/build-plugin.sh
  rm -rf $RELEASE_DIR/CLAUDE.md
  rm -rf $RELEASE_DIR/.claude
  rm -rf $RELEASE_DIR/vendor

  # Install production dependencies
  cd $RELEASE_DIR
  composer install --no-dev --optimize-autoloader --no-interaction
  cd ../..

  # Create ZIP
  cd $BUILD_DIR
  zip -r ../$PLUGIN_NAME.zip $PLUGIN_NAME/
  cd ..

  # Clean up
  rm -rf $BUILD_DIR

  echo "Plugin built successfully: $PLUGIN_NAME.zip"