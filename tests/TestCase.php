<?php namespace PHPNE\Hierarchy\Tests;

use PDO;
use PHPNE\Hierarchy\Folder;
use PHPUnit_Framework_TestCase;

abstract class TestCase extends PHPUnit_Framework_TestCase
{
    /**
     * @var PDO
     */
    protected $conn;

    /**
     * Create the seed data
     *
     * @return array
     */
    public function seed()
    {
        $conn = $this->conn();

        $root         = Folder::create($conn, 'Acme Adventures');
        $grandparent1 = Folder::create($conn, 'Outdoors', $root->id);
        $grandparent2 = Folder::create($conn, 'Equipment', $root->id);
        $parent1      = Folder::create($conn, 'Rock Climbing', $grandparent1->id);
        $parent2      = Folder::create($conn, 'Safety', $grandparent2->id);
        $child        = Folder::create($conn, 'Helmets', $parent1->id);

        return [$root, $grandparent1, $grandparent2, $parent1, $parent2, $child];
    }

    /**
     * Set up the required database tables
     *
     * @return void
     */
    protected function setUp()
    {
        $conn = $this->conn();

        $this->setUpFoldersTable($conn);
        $this->setUpPathsTable($conn);
    }

    /**
     * Drop the database tables
     *
     * @return void
     */
    protected function tearDown()
    {
        $conn = $this->conn();

        $conn->exec('DROP TABLE IF EXISTS folders, paths');
    }

    /**
     * Create or return the PDO
     *
     * @return PDO
     */
    protected function conn()
    {
        if ($this->conn) return $this->conn;

        $conn = new PDO('mysql:dbname=test;host=localhost','user','password');
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $this->conn = $conn;
    }

    /**
     * Set up the `folders` table
     *
     * @param PDO $conn
     * @return void
     */
    protected function setUpFoldersTable(PDO $conn)
    {
        $conn->exec('
            CREATE TABLE IF NOT EXISTS `folders` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
              `parent_id` int(10) unsigned DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ');
    }

    /**
     * Set up the `paths` table
     *
     * @param PDO $conn
     * @return void
     */
    protected function setUpPathsTable(PDO $conn)
    {
        $conn->exec('
            CREATE TABLE IF NOT EXISTS `paths` (
              `ancestor_id` int(10) unsigned NOT NULL,
              `descendant_id` int(10) unsigned NOT NULL,
              `depth` int(10) unsigned NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ');
    }

    /**
     * Get the paths
     *
     * @return array
     */
    protected function paths()
    {
        $statement = $this->conn()->prepare('SELECT * FROM paths');
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        $statement->execute();

        return $statement->fetchAll();
    }
}
