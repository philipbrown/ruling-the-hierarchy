<?php namespace PHPNE\Hierarchy\Tests;

use PDO;
use PHPNE\Hierarchy\Folder;

class FolderTest extends TestCase
{
    /** @test */
    public function should_create_folder()
    {
        $folder = Folder::create($this->conn(), 'My Folder');

        $this->assertEquals('1', $folder->id);
        $this->assertEquals('My Folder', $folder->name);
        $this->assertNull($folder->parent_id);
    }

    /** @test */
    public function should_set_folder_paths()
    {
        // Create the root folder
        $root  = Folder::create($this->conn(), 'Root');
        $paths = $this->paths();
        $this->assertEquals(1, count($paths));
        $this->assertEquals(
            ['ancestor_id' => '1','descendant_id' => '1', 'depth' => '0'], $paths[0]);

        // Create the parent folder
        $parent = Folder::create($this->conn(), 'Parent', $root->id);
        $paths  = $this->paths();
        $this->assertEquals(3, count($paths));
        $this->assertEquals(
            ['ancestor_id' => '1', 'descendant_id' => '2', 'depth' => '1'], $paths[1]);
        $this->assertEquals(
            ['ancestor_id' => '2', 'descendant_id' => '2', 'depth' => '0'], $paths[2]);

        // Create the child folder
        $child = Folder::create($this->conn(), 'Child', $parent->id);
        $paths = $this->paths();
        $this->assertEquals(6, count($paths));
        $this->assertEquals(
            ['ancestor_id' => '1', 'descendant_id' => '3', 'depth' => '2'], $paths[3]);
        $this->assertEquals(
            ['ancestor_id' => '2', 'descendant_id' => '3', 'depth' => '1'], $paths[4]);
        $this->assertEquals(
            ['ancestor_id' => '3', 'descendant_id' => '3', 'depth' => '0'], $paths[5]);
    }

    /** @test */
    public function should_delete_folder_paths()
    {
        // Create the folders
        $root   = Folder::create($this->conn(), 'Root');
        $parent = Folder::create($this->conn(), 'Parent', $root->id);
        $child  = Folder::create($this->conn(), 'Child',  $parent->id);

        // Get the paths
        $this->assertEquals(6, count($this->paths()));

        $child->delete();

        $paths = $this->paths();
        $this->assertEquals(3, count($paths));
        $this->assertEquals(
            ['ancestor_id' => '1', 'descendant_id' => '2', 'depth' => '1'], $paths[1]);
        $this->assertEquals(
            ['ancestor_id' => '2', 'descendant_id' => '2', 'depth' => '0'], $paths[2]);
    }

    /** @test */
    public function should_move_folder_to_new_parent()
    {
        $folders = $this->seed();

        $child = current(array_filter($folders, function ($folder) {
            return $folder->name == 'Helmets';
        }));
        $parent = current(array_filter($folders, function ($folder) {
             return $folder->name == 'Safety';
        }));

        $child->move($parent->id);

        $paths = $this->paths();

        // Root
        $this->assertEquals(
            ['ancestor_id' => 1, 'descendant_id' => 1, 'depth' => 0], $paths[0]);

        // Grand Parent 1
        $this->assertEquals(
            ['ancestor_id' => 1, 'descendant_id' => 2, 'depth' => 1], $paths[1]);
        $this->assertEquals(
            ['ancestor_id' => 2, 'descendant_id' => 2, 'depth' => 0], $paths[2]);

        // Grand Parent 2
        $this->assertEquals(
            ['ancestor_id' => 1, 'descendant_id' => 3, 'depth' => 1], $paths[3]);
        $this->assertEquals(
            ['ancestor_id' => 3, 'descendant_id' => 3, 'depth' => 0], $paths[4]);

        // Parent 1
        $this->assertEquals(
            ['ancestor_id' => 1, 'descendant_id' => 4, 'depth' => 2], $paths[5]);
        $this->assertEquals(
            ['ancestor_id' => 2, 'descendant_id' => 4, 'depth' => 1], $paths[6]);
        $this->assertEquals(
            ['ancestor_id' => 4, 'descendant_id' => 4, 'depth' => 0], $paths[7]);

        // Parent 2
        $this->assertEquals(
            ['ancestor_id' => 1, 'descendant_id' => 5, 'depth' => 2], $paths[8]);
        $this->assertEquals(
            ['ancestor_id' => 3, 'descendant_id' => 5, 'depth' => 1], $paths[9]);
        $this->assertEquals(
            ['ancestor_id' => 5, 'descendant_id' => 5, 'depth' => 0], $paths[10]);

        // Child
        $this->assertEquals(
            ['ancestor_id' => 6, 'descendant_id' => 6, 'depth' => 0], $paths[11]);
        $this->assertEquals(
            ['ancestor_id' => 1, 'descendant_id' => 6, 'depth' => 3], $paths[12]);
        $this->assertEquals(
            ['ancestor_id' => 3, 'descendant_id' => 6, 'depth' => 2], $paths[13]);
        $this->assertEquals(
            ['ancestor_id' => 5, 'descendant_id' => 6, 'depth' => 1], $paths[14]);
    }

    /** @test */
    public function should_get_children()
    {
        $folders = $this->seed();

        $root = current(array_filter($folders, function ($folder) {
            return $folder->name == 'Acme Adventures';
        }));

        $children = $root->children();

        $this->assertEquals(2, count($children));
        $this->assertEquals('Outdoors',  $children[0]['name']);
        $this->assertEquals('Equipment', $children[1]['name']);
    }

    /** @test */
    public function should_get_siblings()
    {
        $folders = $this->seed();

        $folder = current(array_filter($folders, function ($folder) {
            return $folder->name == 'Outdoors';
        }));

        $siblings = $folder->siblings();

        $this->assertEquals(1, count($siblings));
        $this->assertEquals('Equipment', $siblings[0]['name']);
    }

    /** @test */
    public function should_get_ancestors()
    {
        $folders = $this->seed();

        $folder = current(array_filter($folders, function ($folder) {
            return $folder->name == 'Helmets';
        }));

        $ancestors = $folder->ancestors();

        $this->assertEquals(3, count($ancestors));
        $this->assertEquals('Acme Adventures', $ancestors[0]['name']);
        $this->assertEquals('Outdoors',      $ancestors[1]['name']);
        $this->assertEquals('Rock Climbing', $ancestors[2]['name']);
    }

    /** @test */
    public function should_get_descendants()
    {
        $folders = $this->seed();

        $folder = current(array_filter($folders, function ($folder) {
            return $folder->name == 'Outdoors';
        }));

        $descendants = $folder->descendants();

        $this->assertEquals(2, count($descendants));
        $this->assertEquals('Rock Climbing', $descendants[0]['name']);
        $this->assertEquals('Helmets',       $descendants[1]['name']);
    }
}
