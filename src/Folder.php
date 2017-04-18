<?php namespace PHPNE\Hierarchy;

use PDO;

class Folder
{
    /**
     * @var PDO
     */
    private $conn;

    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $parent_id;

    /**
     * @param PDO
     */
    private function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Create a new Folder
     *
     * @param PDO $conn
     * @param int $parent_id
     * @return Folder
     */
    public static function create(PDO $conn, $name, $parent_id = null)
    {
        // Insert the row
        $statement = $conn->prepare('
            INSERT INTO folders (name, parent_id) VALUES(?, ?)');
        $statement->execute([$name, $parent_id]);

        // Get the id
        $id = $conn->lastInsertId();

        // Create the folder
        $folder            = new self($conn);
        $folder->id        = $id;
        $folder->name      = $name;
        $folder->parent_id = $parent_id;

        // Set the paths
        $folder->setFolderPaths();

        return $folder;
    }

    /**
     * Delete the Folder
     *
     * @return void
     */
    public function delete()
    {
        $this->deleteFolderPaths();

        $statement = $this->conn->prepare('DELETE FROM folders WHERE id = ?');
        $statement->execute([$this->id]);
    }

    /**
     * Move the Folder to a new parent
     *
     * @param string $parent_id
     * @return void
     */
    public function move($parent_id)
    {
        $statement = $this->conn->prepare('
            UPDATE folders SET parent_id = ? WHERE id = ?');

        $statement->execute([$parent_id, $this->id]);

        $this->parent_id = $parent_id;

        $this->resetFolderPaths();
    }

    /**
     * Get the children of a Folder
     *
     * @return array
     */
    public function children()
    {
        $statement = $this->conn->prepare('
            SELECT * FROM folders WHERE parent_id = ?');

        $statement->execute([$this->id]);

        return $statement->fetchAll();
    }

    /**
     * Get the siblings of a Folder
     *
     * @return array
     */
    public function siblings()
    {
        $statement = $this->conn->prepare('
            SELECT * FROM folders WHERE parent_id = ? AND id != ?');

        $statement->execute([$this->parent_id, $this->id]);

        return $statement->fetchAll();
    }

    /**
     * Get the ancestors of a Folder
     *
     * @return array
     */
    public function ancestors()
    {
        $statement = $this->conn->prepare('
            SELECT folders.* FROM folders
            INNER JOIN paths ON paths.ancestor_id = folders.id
            WHERE paths.descendant_id = ?
            AND paths.depth > 0
        ');

        $statement->execute([$this->id]);

        return $statement->fetchAll();
    }

    /**
     * Get the descendants of a Folder
     *
     * @return array
     */
    public function descendants()
    {
        $statement = $this->conn->prepare('
            SELECT folders.* FROM folders
            INNER JOIN paths ON paths.descendant_id = folders.id
            WHERE paths.ancestor_id = ?
            AND paths.depth > 0
        ');

        $statement->execute([$this->id]);

        return $statement->fetchAll();
    }

    /**
     * Set the folder paths
     *
     * @return void
     */
    private function setFolderPaths()
    {
        $descendant = $this->id;

        $ancestor = $this->parent_id ? $this->parent_id : $descendant;

        $statement = $this->conn->prepare('
            INSERT INTO paths (ancestor_id, descendant_id, depth)
            SELECT ancestor_id, ?, depth+1
            FROM paths
            WHERE descendant_id = ?
            UNION ALL SELECT ?, ?, 0
        ');
        $statement->execute([$descendant, $ancestor, $descendant, $descendant]);
    }

    /**
     * Delete the folder paths
     *
     * @return void
     */
    private function deleteFolderPaths()
    {
        $statement = $this->conn->prepare('
            DELETE FROM paths
            WHERE descendant_id IN (
                SELECT descendant_id FROM (
                    SELECT descendant_id FROM paths
                    WHERE ancestor_id = ?
                ) as tmptable
            )
        ');

        $statement->execute([$this->id]);
    }

    /**
     * Reset the folder paths
     *
     * @return void
     */
    private function resetFolderPaths()
    {
        // Unbind the existing paths
        $statement = $this->conn->prepare('
            DELETE FROM paths
            WHERE descendant_id IN (
                SELECT d FROM (
                    SELECT descendant_id as d FROM paths
                    WHERE ancestor_id = ?
                ) as dct
            )
            AND ancestor_id IN (
                SELECT a FROM (
                    SELECT ancestor_id AS a FROM paths
                    WHERE descendant_id = ?
                    AND ancestor_id <> ?
                ) as ct
            )
        ');
        $statement->execute([$this->id, $this->id, $this->id]);

        // Bind the new paths
        $statement = $this->conn->prepare('
            INSERT INTO paths (ancestor_id, descendant_id, depth)
            SELECT supertbl.ancestor_id, subtbl.descendant_id, supertbl.depth+subtbl.depth+1
            FROM paths as supertbl
            CROSS JOIN paths as subtbl
            WHERE supertbl.descendant_id = ?
            AND subtbl.ancestor_id = ?
        ');

        $statement->execute([$this->parent_id, $this->id]);
    }

    /**
     * Get the properties of the Folder
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        if (in_array($key, ['id', 'name', 'parent_id'])) {
            return $this->$key;
        }
    }
}
