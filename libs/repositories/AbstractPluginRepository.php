<?php

abstract class AbstractPluginRepository extends BaseRepository
{
    abstract function getPluginsDirectoryPath();
    abstract function getClassNameThatPluginsMustExtend();

    abstract protected function getPluginInfoFromDatabase($name, $module_srl);
    abstract protected function updatePluginInfo($plugin);
    abstract protected function insertPluginInfo(AbstractPlugin $plugin);
    abstract protected function deletePluginInfo($name, $module_srl);
    abstract protected function getAllPluginsInDatabase($module_srl, $args);
    abstract protected function getAllActivePluginsInDatabase($module_srl);
    abstract protected function fixPlugin($name, $old_module_srl, $new_module_srl);
	abstract protected function updatePluginsAllButThis($is_default, $name, $module_srl);

    protected function getPluginInstanceByName($plugin_name, $module_srl)
    {
        // Skip files (we are only interested in the folders)
        if(!is_dir($this->getPluginsDirectoryPath() . DIRECTORY_SEPARATOR . $plugin_name))
        {
            throw new Exception("Given folder name is not a directory");
        }

        // Convert from under_scores to CamelCase in order to get class name
        $plugin_class_name = str_replace(' ', '', ucwords(str_replace('_', ' ', $plugin_name)));
        $plugin_class_path = $this->getPluginsDirectoryPath()
            . DIRECTORY_SEPARATOR . $plugin_name
            . DIRECTORY_SEPARATOR . $plugin_class_name . '.php';

        if(!file_exists($plugin_class_path)) {
            throw new Exception("Plugin class was not found in given folder");
        };

        // Include class and check if it extends the required abstract class
        require_once $plugin_class_path;

        $plugin_instance = new $plugin_class_name;
        $class_name_that_plugin_must_extend = $this->getClassNameThatPluginsMustExtend();
        if(!($plugin_instance instanceof $class_name_that_plugin_must_extend))
        {
            throw new Exception("Plugin class does not extend required $class_name_that_plugin_must_extend");
        };

        $plugin_instance->module_srl = $module_srl;
        return $plugin_instance;
    }

    private function getPluginsByFolder()
    {
        return FileHandler::readDir($this->getPluginsDirectoryPath());
    }

    protected function getPluginInstanceFromProperties($data)
    {
        $data->properties = unserialize($data->props);
        unset($data->props);

        $plugin = $this->getPluginInstanceByName($data->name, $data->module_srl);
        $plugin->setProperties($data);
        return $plugin;
    }

    public function getPlugin($name, $module_srl)
    {
        $data = $this->getPluginInfoFromDatabase($name, $module_srl);

        // Update code; add module srl to plugins that have module_srl = 0
        // TODO Remove this when releasing XE Shop
        if(!$data)
        {
            $data = $this->getPluginInfoFromDatabase($name, 0);
            if($data)
            {
                ShopLogger::log("Upgrading plugin $name - setting module_srl from 0 to $module_srl");
                $this->fixPlugin($data->name, 0, $module_srl);
                $data->module_srl = $module_srl;
            }
        }

        // If plugin exists in the database, return it as is
        if($data)
        {
            return $this->getPluginInstanceFromProperties($data);
        }

        // Otherwise, initialize it with info from the extension class and insert in database
        $plugin = $this->getPluginInstanceByName($name, $module_srl);

        $this->insertPlugin($plugin);

        return $this->getPlugin($name, $module_srl);
    }

    public function installPlugin($name, $module_srl)
    {
        return $this->getPlugin($name, $module_srl);
    }

    /**
     * Returns all available plugins
     *
     * Looks in the database and also in the plugins folder to see
     * if any new extension showed up. If yes, also adds it in the database
     */
    public function getAvailablePlugins($module_srl)
    {
        // Scan through the plugins_shipping extension directory to retrieve available methods
        $extensions = $this->getPluginsByFolder();

        $plugins = array();
        foreach($extensions as $extension_name)
        {
            try
            {
                $plugins[] = $this->getPlugin($extension_name, $module_srl);
            }
            catch(Exception $e)
            {
                continue;
            }
        }

        return $plugins;
    }

    /**
     * Get all enabled plugins
     */
    public function getActivePlugins($module_srl)
    {
        $plugins_info = $this->getAllActivePluginsInDatabase($module_srl);

        $active_plugins = array();
        foreach($plugins_info as $data)
        {
            try
            {
                $active_plugins[] = $this->getPluginInstanceFromProperties($data);
            }
            catch(Exception $e)
            {
                continue;
            }
        }

        return $active_plugins;
    }

    /**
     *
     * Updates a plugin
     *
     * Status: active = 1; inactive = 0
     *
     * @author Daniel Ionescu (dev@xpressengine.org)
     * @param  $plugin
     * @throws exception
     * @return boolean
     */
    public function updatePlugin(AbstractPlugin $plugin)
    {
        if(!isset($plugin->name))
        {
            throw new Exception("Please provide the name of the element you want to update");
        }
        if(isset($plugin->properties) && !is_string($plugin->properties))
        {
            $serialized_properties = serialize($plugin->properties);
            $plugin->properties = $serialized_properties;
        }

        $this->updatePluginInfo($plugin);
    }

    public function insertPlugin($plugin)
    {
        $this->insertPluginInfo($plugin);
    }

    public function deletePlugin($name, $module_srl)
    {
        $this->deletePluginInfo($name, $module_srl);
    }

	public function setDefault($name, $module_srl)
	{
		if(!isset($name) || !isset($module_srl))
		{
			return new ArgumentException("You must provide name and module_srl for making a plugin default.");
		}
		$plugin = $this->getPlugin($name, $module_srl);
		if(!$plugin->isActive())
		{
			return new ArgumentException("It is not allowed to set as default an inactive plugin");
		}

		// Update all other plugins with is_default = 0
		$this->updatePluginsAllButThis(0, $name, $module_srl);

		// Set this plugin is_default = 1
		$plugin->is_default = 1;
		$this->updatePlugin($plugin);
	}

	public function getDefault($module_srl)
	{
		$args = new stdClass();
		$args->is_default = 1;
		$plugin_info = $this->getAllPluginsInDatabase($module_srl, $args);
		return $this->getPluginInstanceFromProperties($plugin_info[0]);
	}

    /**
     * Deletes plugins from DB if they do not have a folder with a corresponding name
     */
    public function sanitizePlugins($module_srl) {
        $pgByDatabase = $this->getAllPluginsInDatabase($module_srl, null);
        $pgByFolders = $this->getPluginsByFolder();

        foreach ($pgByDatabase as $obj) {
            if (!in_array($obj->name,$pgByFolders)) {
                $this->deletePlugin($obj->name, $module_srl);
            }
        }
    }
}

