<?php
require_once __DIR__ . '/../config/setPath.php';

 
require_once __DIR__ . '/Database.php';

class Portfolio {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists() { 
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        if ($driver === 'sqlite') {
            $query = "CREATE TABLE IF NOT EXISTS portfolio_projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT,
                image TEXT,
                project_url TEXT,
                github_url TEXT,
                technologies TEXT,
                category TEXT,
                completion_date TEXT,
                featured INTEGER DEFAULT 0,
                created_by INTEGER,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            $query = "CREATE TABLE IF NOT EXISTS portfolio_projects (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                image VARCHAR(255),
                project_url VARCHAR(255),
                github_url VARCHAR(255),
                technologies VARCHAR(255),
                category VARCHAR(255),
                completion_date DATE,
                featured BOOLEAN DEFAULT FALSE,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
        }
        
        $this->db->exec($query);

        if ($driver !== 'sqlite') {
            try {
                $this->db->exec("ALTER TABLE portfolio_projects ADD COLUMN category VARCHAR(255)");
            } catch (\PDOException $e) {
            }
        } else {
            try {
                $this->db->exec("ALTER TABLE portfolio_projects ADD COLUMN category TEXT");
            } catch (\PDOException $e) {
            }
        }
    }

    public function getAllProjects() {
        $query = "SELECT * FROM portfolio_projects ORDER BY created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentProjects($limit = 5) {
        $query = "SELECT * FROM portfolio_projects ORDER BY created_at DESC LIMIT :limit";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProjectById($id) {
        $query = "SELECT * FROM portfolio_projects WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createProject($data) {
        requireAdminSession(true);
        $query = "INSERT INTO portfolio_projects (
                    title, description, image, project_url, github_url, technologies, category, completion_date, featured, created_by
                ) VALUES (
                    :title, :description, :image, :project_url, :github_url, :technologies, :category, :completion_date, :featured, :created_by
                )";
        
        $stmt = $this->db->prepare($query);
        
        $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['description'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':image', $data['image'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':project_url', $data['project_url'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':github_url', $data['github_url'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':technologies', $data['technologies'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':category', $data['category'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':completion_date', $data['completion_date'] ?? date('Y-m-d'), PDO::PARAM_STR);
        $stmt->bindValue(':featured', $data['featured'] ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':created_by', $_SESSION['user_id'] ?? 1, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    public function updateProject($id, $data) {
        requireAdminSession(true);
        $query = "UPDATE portfolio_projects SET 
                  title = :title, 
                  description = :description, 
                  project_url = :project_url, 
                  github_url = :github_url, 
                  technologies = :technologies, 
                  category = :category,
                  completion_date = :completion_date, 
                  featured = :featured";
        
        if (isset($data['image']) && $data['image']) {
            $query .= ", image = :image";
        }
        
        $query .= " WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['description'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':project_url', $data['project_url'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':github_url', $data['github_url'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':technologies', $data['technologies'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':category', $data['category'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':completion_date', $data['completion_date'] ?? date('Y-m-d'), PDO::PARAM_STR);
        $stmt->bindValue(':featured', $data['featured'] ?? 0, PDO::PARAM_INT);
        
        if (isset($data['image']) && $data['image']) {
            $stmt->bindValue(':image', $data['image'], PDO::PARAM_STR);
        }
        
        return $stmt->execute();
    }

    public function deleteProject($id) {
        requireAdminSession(true);
        $project = $this->getProjectById($id);
        
        $query = "DELETE FROM portfolio_projects WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $result = $stmt->execute();
        
        if ($result && $project && !empty($project['image'])) {
            $imagePath = __DIR__ . '/../' . $project['image'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        
        return $result;
    }

    public function getFeaturedProjects($limit = 3) {
        $query = "SELECT * FROM portfolio_projects WHERE featured = 1 ORDER BY completion_date DESC LIMIT :limit";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalProjects() {
        $query = "SELECT COUNT(*) FROM portfolio_projects";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchColumn();
    }

    public function getDistinctCategories() {
        try {
            $query = "SELECT DISTINCT category FROM portfolio_projects WHERE category IS NOT NULL AND category != '' ORDER BY category ASC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\PDOException $e) {
            return [];
        }
    }

    public function getDistinctYears() {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $query = "SELECT DISTINCT CAST(strftime('%Y', completion_date) AS INTEGER) AS year FROM portfolio_projects WHERE completion_date IS NOT NULL AND completion_date != '' ORDER BY year DESC";
        } else {
            $query = "SELECT DISTINCT YEAR(completion_date) AS year FROM portfolio_projects WHERE completion_date IS NOT NULL AND completion_date != '' ORDER BY year DESC";
        }
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public function getPaginatedProjects($offset = 0, $limit = 10) {
        $query = "SELECT * FROM portfolio_projects ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}