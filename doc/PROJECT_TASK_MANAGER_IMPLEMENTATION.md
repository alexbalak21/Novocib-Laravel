# Project Task Manager - Implementation Guide

## Overview

The Project Task Manager is an internal microservice for admin/super users to track projects, manage tasks, and log time spent on each task with detailed notes. This service will be integrated into the existing admin panel at `/admin/task-manager`.

## Features

- **Project Management**: Create, edit, delete projects with descriptions
- **Task Management**: Organize tasks within projects with status tracking
- **Time Tracking**: Log time entries with date, time, and duration
- **Task Notes**: Add detailed notes to each time entry
- **Reporting**: View time summaries per project/task
- **Admin Only**: Access restricted to authenticated admin users

## Database Schema

### 1. Projects Table

```sql
CREATE TABLE projects (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  status ENUM('active', 'paused', 'completed', 'archived') DEFAULT 'active',
  start_date DATE,
  end_date DATE,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;
```

### 2. Tasks Table

```sql
CREATE TABLE tasks (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  status ENUM('todo', 'in-progress', 'completed', 'blocked') DEFAULT 'todo',
  priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
  estimated_hours DECIMAL(8,2),
  assigned_to INT,
  due_date DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_to) REFERENCES users(id)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;
```

### 3. Time Entries Table

```sql
CREATE TABLE time_entries (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  task_id INT NOT NULL,
  project_id INT NOT NULL,
  user_id INT NOT NULL,
  date DATE NOT NULL,
  start_time TIME,
  end_time TIME,
  duration_hours DECIMAL(8,2) NOT NULL,
  notes LONGTEXT,
  billable BOOLEAN DEFAULT true,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_project_id ON time_entries(project_id);
CREATE INDEX idx_task_id ON time_entries(task_id);
CREATE INDEX idx_user_id ON time_entries(user_id);
CREATE INDEX idx_date ON time_entries(date);
```

### 4. SQL Migration File

Create `sql/project_task_manager.sql`:

```sql
-- Projects
CREATE TABLE projects (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  status ENUM('active', 'paused', 'completed', 'archived') DEFAULT 'active',
  start_date DATE,
  end_date DATE,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tasks
CREATE TABLE tasks (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  status ENUM('todo', 'in-progress', 'completed', 'blocked') DEFAULT 'todo',
  priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
  estimated_hours DECIMAL(8,2),
  assigned_to INT,
  due_date DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_to) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Time Entries
CREATE TABLE time_entries (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  task_id INT NOT NULL,
  project_id INT NOT NULL,
  user_id INT NOT NULL,
  date DATE NOT NULL,
  start_time TIME,
  end_time TIME,
  duration_hours DECIMAL(8,2) NOT NULL,
  notes LONGTEXT,
  billable BOOLEAN DEFAULT true,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_project_id (project_id),
  INDEX idx_task_id (task_id),
  INDEX idx_user_id (user_id),
  INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Directory Structure

```
app/
├── internal/
│   └── admin/
│       └── task-manager/
│           ├── index.php                    # Dashboard/project list
│           ├── project.php                  # Project detail view
│           ├── tasks.php                    # Task management
│           ├── time-entries.php             # Time tracking UI
│           ├── reports.php                  # Time reports
│           ├── controllers/
│           │   ├── ProjectController.php
│           │   ├── TaskController.php
│           │   └── TimeEntryController.php
│           └── views/
│               ├── projects/
│               │   ├── list.php
│               │   ├── form.php
│               │   └── detail.php
│               ├── tasks/
│               │   ├── list.php
│               │   ├── form.php
│               │   └── card.php
│               ├── time-entries/
│               │   ├── list.php
│               │   ├── form.php
│               │   └── log.php
│               └── reports/
│                   ├── project-summary.php
│                   └── task-summary.php
├── models/
│   ├── Project.php
│   ├── Task.php
│   └── TimeEntry.php
├── repository/
│   ├── ProjectRepository.php
│   ├── TaskRepository.php
│   └── TimeEntryRepository.php
└── logic/
    ├── task-manager/
    │   ├── create_project.php
    │   ├── update_project.php
    │   ├── create_task.php
    │   ├── log_time.php
    │   ├── update_time_entry.php
    │   ├── delete_time_entry.php
    │   └── get_reports.php
```

## Models

### 1. Project Model (`app/models/Project.php`)

```php
<?php

class Project
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $status,
        public readonly ?string $start_date,
        public readonly ?string $end_date,
        public readonly int $created_by,
        public readonly ?string $created_at,
        public readonly ?string $updated_at
    ) {}

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getDaysRemaining(): ?int
    {
        if (!$this->end_date) return null;
        
        $end = strtotime($this->end_date);
        $today = strtotime(date('Y-m-d'));
        $days = intval(($end - $today) / 86400);
        
        return $days;
    }
}
```

### 2. Task Model (`app/models/Task.php`)

```php
<?php

class Task
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $project_id,
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $status,
        public readonly string $priority,
        public readonly ?float $estimated_hours,
        public readonly ?int $assigned_to,
        public readonly ?string $due_date,
        public readonly ?string $created_at,
        public readonly ?string $updated_at
    ) {}

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    public function getPriorityClass(): string
    {
        return match($this->priority) {
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => 'info',
            'low' => 'secondary',
            default => 'secondary',
        };
    }
}
```

### 3. TimeEntry Model (`app/models/TimeEntry.php`)

```php
<?php

class TimeEntry
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $task_id,
        public readonly int $project_id,
        public readonly int $user_id,
        public readonly string $date,
        public readonly ?string $start_time,
        public readonly ?string $end_time,
        public readonly float $duration_hours,
        public readonly ?string $notes,
        public readonly bool $billable,
        public readonly ?string $created_at,
        public readonly ?string $updated_at
    ) {}

    public function getFormattedDuration(): string
    {
        $hours = intval($this->duration_hours);
        $minutes = intval(($this->duration_hours - $hours) * 60);
        
        return "{$hours}h {$minutes}m";
    }

    public function getTimeRange(): string
    {
        if (!$this->start_time || !$this->end_time) {
            return $this->getFormattedDuration();
        }
        
        return "{$this->start_time} - {$this->end_time}";
    }
}
```

## Repositories

### 1. ProjectRepository (`app/repository/ProjectRepository.php`)

```php
<?php

class ProjectRepository
{
    protected ?PDO $conn;

    public function __construct()
    {
        require_once $_SERVER['DOCUMENT_ROOT'] . "/app/db/connect.php";
        try {
            $this->conn = connect_db();
        } catch (Error $e) {
            echo 'Database Connection Error' . "<br>" . $e->getMessage();
            exit;
        }
    }

    public function create(Project $project): ?int
    {
        $query = "INSERT INTO projects (name, description, status, start_date, end_date, created_by) 
                  VALUES (:name, :description, :status, :start_date, :end_date, :created_by)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':name', $project->name);
        $stmt->bindValue(':description', $project->description);
        $stmt->bindValue(':status', $project->status);
        $stmt->bindValue(':start_date', $project->start_date);
        $stmt->bindValue(':end_date', $project->end_date);
        $stmt->bindValue(':created_by', $project->created_by);
        
        $stmt->execute();
        return $this->conn->lastInsertId();
    }

    public function findById(int $id): ?Project
    {
        $query = "SELECT * FROM projects WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? $this->mapToProject($data) : null;
    }

    public function findAll(string $status = null): array
    {
        $query = "SELECT * FROM projects";
        
        if ($status) {
            $query .= " WHERE status = :status";
        }
        
        $query .= " ORDER BY created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        
        if ($status) {
            $stmt->bindParam(':status', $status);
        }
        
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map([$this, 'mapToProject'], $data);
    }

    public function update(Project $project): bool
    {
        $query = "UPDATE projects 
                  SET name = :name, description = :description, status = :status, 
                      start_date = :start_date, end_date = :end_date 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':name', $project->name);
        $stmt->bindValue(':description', $project->description);
        $stmt->bindValue(':status', $project->status);
        $stmt->bindValue(':start_date', $project->start_date);
        $stmt->bindValue(':end_date', $project->end_date);
        $stmt->bindValue(':id', $project->id);

        return $stmt->execute();
    }

    public function delete(int $id): bool
    {
        $query = "DELETE FROM projects WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    private function mapToProject(array $data): Project
    {
        return new Project(
            $data['id'],
            $data['name'],
            $data['description'],
            $data['status'],
            $data['start_date'],
            $data['end_date'],
            $data['created_by'],
            $data['created_at'],
            $data['updated_at']
        );
    }
}
```

### 2. TaskRepository (`app/repository/TaskRepository.php`)

```php
<?php

class TaskRepository
{
    protected ?PDO $conn;

    public function __construct()
    {
        require_once $_SERVER['DOCUMENT_ROOT'] . "/app/db/connect.php";
        try {
            $this->conn = connect_db();
        } catch (Error $e) {
            echo 'Database Connection Error' . "<br>" . $e->getMessage();
            exit;
        }
    }

    public function create(Task $task): ?int
    {
        $query = "INSERT INTO tasks 
                  (project_id, title, description, status, priority, estimated_hours, assigned_to, due_date) 
                  VALUES (:project_id, :title, :description, :status, :priority, :estimated_hours, :assigned_to, :due_date)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':project_id', $task->project_id);
        $stmt->bindValue(':title', $task->title);
        $stmt->bindValue(':description', $task->description);
        $stmt->bindValue(':status', $task->status);
        $stmt->bindValue(':priority', $task->priority);
        $stmt->bindValue(':estimated_hours', $task->estimated_hours);
        $stmt->bindValue(':assigned_to', $task->assigned_to);
        $stmt->bindValue(':due_date', $task->due_date);
        
        $stmt->execute();
        return $this->conn->lastInsertId();
    }

    public function findByProjectId(int $projectId): array
    {
        $query = "SELECT * FROM tasks WHERE project_id = :project_id ORDER BY priority DESC, due_date ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':project_id', $projectId);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([$this, 'mapToTask'], $data);
    }

    public function findById(int $id): ?Task
    {
        $query = "SELECT * FROM tasks WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? $this->mapToTask($data) : null;
    }

    public function update(Task $task): bool
    {
        $query = "UPDATE tasks 
                  SET title = :title, description = :description, status = :status, 
                      priority = :priority, estimated_hours = :estimated_hours, 
                      assigned_to = :assigned_to, due_date = :due_date 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':title', $task->title);
        $stmt->bindValue(':description', $task->description);
        $stmt->bindValue(':status', $task->status);
        $stmt->bindValue(':priority', $task->priority);
        $stmt->bindValue(':estimated_hours', $task->estimated_hours);
        $stmt->bindValue(':assigned_to', $task->assigned_to);
        $stmt->bindValue(':due_date', $task->due_date);
        $stmt->bindValue(':id', $task->id);

        return $stmt->execute();
    }

    public function delete(int $id): bool
    {
        $query = "DELETE FROM tasks WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    private function mapToTask(array $data): Task
    {
        return new Task(
            $data['id'],
            $data['project_id'],
            $data['title'],
            $data['description'],
            $data['status'],
            $data['priority'],
            $data['estimated_hours'],
            $data['assigned_to'],
            $data['due_date'],
            $data['created_at'],
            $data['updated_at']
        );
    }
}
```

### 3. TimeEntryRepository (`app/repository/TimeEntryRepository.php`)

```php
<?php

class TimeEntryRepository
{
    protected ?PDO $conn;

    public function __construct()
    {
        require_once $_SERVER['DOCUMENT_ROOT'] . "/app/db/connect.php";
        try {
            $this->conn = connect_db();
        } catch (Error $e) {
            echo 'Database Connection Error' . "<br>" . $e->getMessage();
            exit;
        }
    }

    public function create(TimeEntry $entry): ?int
    {
        $query = "INSERT INTO time_entries 
                  (task_id, project_id, user_id, date, start_time, end_time, duration_hours, notes, billable) 
                  VALUES (:task_id, :project_id, :user_id, :date, :start_time, :end_time, :duration_hours, :notes, :billable)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':task_id', $entry->task_id);
        $stmt->bindValue(':project_id', $entry->project_id);
        $stmt->bindValue(':user_id', $entry->user_id);
        $stmt->bindValue(':date', $entry->date);
        $stmt->bindValue(':start_time', $entry->start_time);
        $stmt->bindValue(':end_time', $entry->end_time);
        $stmt->bindValue(':duration_hours', $entry->duration_hours);
        $stmt->bindValue(':notes', $entry->notes);
        $stmt->bindValue(':billable', $entry->billable ? 1 : 0);
        
        $stmt->execute();
        return $this->conn->lastInsertId();
    }

    public function findByTaskId(int $taskId): array
    {
        $query = "SELECT * FROM time_entries WHERE task_id = :task_id ORDER BY date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':task_id', $taskId);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([$this, 'mapToTimeEntry'], $data);
    }

    public function findByProjectId(int $projectId, string $startDate = null, string $endDate = null): array
    {
        $query = "SELECT * FROM time_entries WHERE project_id = :project_id";
        
        if ($startDate && $endDate) {
            $query .= " AND date BETWEEN :start_date AND :end_date";
        }
        
        $query .= " ORDER BY date DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':project_id', $projectId);
        
        if ($startDate && $endDate) {
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
        }
        
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map([$this, 'mapToTimeEntry'], $data);
    }

    public function findById(int $id): ?TimeEntry
    {
        $query = "SELECT * FROM time_entries WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? $this->mapToTimeEntry($data) : null;
    }

    public function update(TimeEntry $entry): bool
    {
        $query = "UPDATE time_entries 
                  SET task_id = :task_id, date = :date, start_time = :start_time, 
                      end_time = :end_time, duration_hours = :duration_hours, 
                      notes = :notes, billable = :billable 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':task_id', $entry->task_id);
        $stmt->bindValue(':date', $entry->date);
        $stmt->bindValue(':start_time', $entry->start_time);
        $stmt->bindValue(':end_time', $entry->end_time);
        $stmt->bindValue(':duration_hours', $entry->duration_hours);
        $stmt->bindValue(':notes', $entry->notes);
        $stmt->bindValue(':billable', $entry->billable ? 1 : 0);
        $stmt->bindValue(':id', $entry->id);

        return $stmt->execute();
    }

    public function delete(int $id): bool
    {
        $query = "DELETE FROM time_entries WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function getTotalHoursByProject(int $projectId, string $startDate = null, string $endDate = null): float
    {
        $query = "SELECT SUM(duration_hours) as total FROM time_entries WHERE project_id = :project_id";
        
        if ($startDate && $endDate) {
            $query .= " AND date BETWEEN :start_date AND :end_date";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':project_id', $projectId);
        
        if ($startDate && $endDate) {
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['total'] ? floatval($result['total']) : 0.0;
    }

    public function getTotalHoursByTask(int $taskId): float
    {
        $query = "SELECT SUM(duration_hours) as total FROM time_entries WHERE task_id = :task_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':task_id', $taskId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['total'] ? floatval($result['total']) : 0.0;
    }

    private function mapToTimeEntry(array $data): TimeEntry
    {
        return new TimeEntry(
            $data['id'],
            $data['task_id'],
            $data['project_id'],
            $data['user_id'],
            $data['date'],
            $data['start_time'],
            $data['end_time'],
            floatval($data['duration_hours']),
            $data['notes'],
            boolval($data['billable']),
            $data['created_at'],
            $data['updated_at']
        );
    }
}
```

## Controllers

### 1. ProjectController (`app/internal/admin/controllers/ProjectController.php`)

```php
<?php

class ProjectController
{
    private ProjectRepository $projectRepo;

    public function __construct()
    {
        require_once $_SERVER['DOCUMENT_ROOT'] . "/app/repository/ProjectRepository.php";
        $this->projectRepo = new ProjectRepository();
    }

    public function index()
    {
        $projects = $this->projectRepo->findAll();
        return compact('projects');
    }

    public function create()
    {
        // Form display logic
        return [];
    }

    public function store($data)
    {
        $project = new Project(
            null,
            $data['name'],
            $data['description'] ?? null,
            $data['status'] ?? 'active',
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
            $_SESSION['user_id'],
            null,
            null
        );

        $id = $this->projectRepo->create($project);
        
        return [
            'success' => $id !== null,
            'project_id' => $id,
            'message' => $id ? 'Project created successfully' : 'Failed to create project'
        ];
    }

    public function show($id)
    {
        $project = $this->projectRepo->findById($id);
        
        if (!$project) {
            return ['error' => 'Project not found', 'status' => 404];
        }

        require_once $_SERVER['DOCUMENT_ROOT'] . "/app/repository/TaskRepository.php";
        $taskRepo = new TaskRepository();
        $tasks = $taskRepo->findByProjectId($id);

        return compact('project', 'tasks');
    }

    public function edit($id)
    {
        $project = $this->projectRepo->findById($id);
        
        if (!$project) {
            return ['error' => 'Project not found', 'status' => 404];
        }

        return compact('project');
    }

    public function update($id, $data)
    {
        $project = $this->projectRepo->findById($id);
        
        if (!$project) {
            return ['error' => 'Project not found', 'status' => 404];
        }

        $updated = new Project(
            $id,
            $data['name'] ?? $project->name,
            $data['description'] ?? $project->description,
            $data['status'] ?? $project->status,
            $data['start_date'] ?? $project->start_date,
            $data['end_date'] ?? $project->end_date,
            $project->created_by,
            $project->created_at,
            date('Y-m-d H:i:s')
        );

        $success = $this->projectRepo->update($updated);
        
        return [
            'success' => $success,
            'message' => $success ? 'Project updated successfully' : 'Failed to update project'
        ];
    }

    public function delete($id)
    {
        $project = $this->projectRepo->findById($id);
        
        if (!$project) {
            return ['error' => 'Project not found', 'status' => 404];
        }

        $success = $this->projectRepo->delete($id);
        
        return [
            'success' => $success,
            'message' => $success ? 'Project deleted successfully' : 'Failed to delete project'
        ];
    }
}
```

### 2. TimeEntryController (`app/internal/admin/controllers/TimeEntryController.php`)

```php
<?php

class TimeEntryController
{
    private TimeEntryRepository $entryRepo;
    private TaskRepository $taskRepo;

    public function __construct()
    {
        require_once $_SERVER['DOCUMENT_ROOT'] . "/app/repository/TimeEntryRepository.php";
        require_once $_SERVER['DOCUMENT_ROOT'] . "/app/repository/TaskRepository.php";
        $this->entryRepo = new TimeEntryRepository();
        $this->taskRepo = new TaskRepository();
    }

    public function logTime($data)
    {
        // Calculate duration if start/end times provided
        $duration = $data['duration_hours'] ?? null;
        
        if (!$duration && $data['start_time'] && $data['end_time']) {
            $start = strtotime($data['start_time']);
            $end = strtotime($data['end_time']);
            $duration = ($end - $start) / 3600; // Convert seconds to hours
        }

        if (!$duration || $duration <= 0) {
            return [
                'success' => false,
                'message' => 'Invalid duration'
            ];
        }

        $entry = new TimeEntry(
            null,
            $data['task_id'],
            $data['project_id'],
            $_SESSION['user_id'],
            $data['date'],
            $data['start_time'] ?? null,
            $data['end_time'] ?? null,
            floatval($duration),
            $data['notes'] ?? null,
            $data['billable'] ?? true,
            null,
            null
        );

        $id = $this->entryRepo->create($entry);
        
        return [
            'success' => $id !== null,
            'entry_id' => $id,
            'message' => $id ? 'Time logged successfully' : 'Failed to log time'
        ];
    }

    public function getTaskEntries($taskId)
    {
        $entries = $this->entryRepo->findByTaskId($taskId);
        $totalHours = $this->entryRepo->getTotalHoursByTask($taskId);
        
        return compact('entries', 'totalHours');
    }

    public function update($id, $data)
    {
        $entry = $this->entryRepo->findById($id);
        
        if (!$entry) {
            return ['error' => 'Time entry not found', 'status' => 404];
        }

        // Recalculate duration if needed
        $duration = $data['duration_hours'] ?? $entry->duration_hours;
        
        if ($data['start_time'] && $data['end_time']) {
            $start = strtotime($data['start_time']);
            $end = strtotime($data['end_time']);
            $duration = ($end - $start) / 3600;
        }

        $updated = new TimeEntry(
            $id,
            $data['task_id'] ?? $entry->task_id,
            $data['project_id'] ?? $entry->project_id,
            $entry->user_id,
            $data['date'] ?? $entry->date,
            $data['start_time'] ?? $entry->start_time,
            $data['end_time'] ?? $entry->end_time,
            floatval($duration),
            $data['notes'] ?? $entry->notes,
            $data['billable'] ?? $entry->billable,
            $entry->created_at,
            date('Y-m-d H:i:s')
        );

        $success = $this->entryRepo->update($updated);
        
        return [
            'success' => $success,
            'message' => $success ? 'Time entry updated' : 'Failed to update'
        ];
    }

    public function delete($id)
    {
        $entry = $this->entryRepo->findById($id);
        
        if (!$entry) {
            return ['error' => 'Time entry not found', 'status' => 404];
        }

        $success = $this->entryRepo->delete($id);
        
        return [
            'success' => $success,
            'message' => $success ? 'Time entry deleted' : 'Failed to delete'
        ];
    }
}
```

## Routes

Add to `routes.php`:

```php
// Task Manager Routes (Admin only)
'/admin/task-manager' => 'app/internal/admin/task-manager/index.php',
'/admin/task-manager/projects' => 'app/internal/admin/task-manager/projects.php',
'/admin/task-manager/project/:id' => 'app/internal/admin/task-manager/project.php',
'/admin/task-manager/tasks' => 'app/internal/admin/task-manager/tasks.php',
'/admin/task-manager/time-entries' => 'app/internal/admin/task-manager/time-entries.php',
'/admin/task-manager/reports' => 'app/internal/admin/task-manager/reports.php',

// API endpoints for AJAX
'/admin/api/projects/create' => 'app/logic/task-manager/create_project.php',
'/admin/api/projects/update' => 'app/logic/task-manager/update_project.php',
'/admin/api/tasks/create' => 'app/logic/task-manager/create_task.php',
'/admin/api/time/log' => 'app/logic/task-manager/log_time.php',
'/admin/api/time/update' => 'app/logic/task-manager/update_time_entry.php',
'/admin/api/time/delete' => 'app/logic/task-manager/delete_time_entry.php',
'/admin/api/reports/project' => 'app/logic/task-manager/get_reports.php',
```

## Key Logic Files

### Log Time Entry (`app/logic/task-manager/log_time.php`)

```php
<?php

require_once $_SERVER['DOCUMENT_ROOT'] . "/app/internal/admin/controllers/TimeEntryController.php";

// Check admin authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $controller = new TimeEntryController();
    $result = $controller->logTime($data);
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
```

### Generate Reports (`app/logic/task-manager/get_reports.php`)

```php
<?php

require_once $_SERVER['DOCUMENT_ROOT'] . "/app/repository/TimeEntryRepository.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/app/repository/ProjectRepository.php";

// Check admin authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$projectId = $_GET['project_id'] ?? null;
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

if (!$projectId) {
    http_response_code(400);
    echo json_encode(['error' => 'project_id required']);
    exit;
}

$entryRepo = new TimeEntryRepository();
$projectRepo = new ProjectRepository();

$project = $projectRepo->findById($projectId);
if (!$project) {
    http_response_code(404);
    echo json_encode(['error' => 'Project not found']);
    exit;
}

$entries = $entryRepo->findByProjectId($projectId, $startDate, $endDate);
$totalHours = $entryRepo->getTotalHoursByProject($projectId, $startDate, $endDate);

// Group by task
$byTask = [];
foreach ($entries as $entry) {
    $taskId = $entry->task_id;
    if (!isset($byTask[$taskId])) {
        $byTask[$taskId] = ['entries' => [], 'total' => 0];
    }
    $byTask[$taskId]['entries'][] = $entry;
    $byTask[$taskId]['total'] += $entry->duration_hours;
}

header('Content-Type: application/json');
echo json_encode([
    'project' => $project,
    'total_hours' => $totalHours,
    'by_task' => $byTask,
    'entry_count' => count($entries),
    'date_range' => [
        'start' => $startDate,
        'end' => $endDate
    ]
]);
```

## UI Views

### Dashboard View (`app/internal/admin/task-manager/index.php`)

```php
<?php

require_once $_SERVER['DOCUMENT_ROOT'] . "/app/internal/admin/controllers/ProjectController.php";

// Auth check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin'])) {
    header('Location: /admin');
    exit;
}

$controller = new ProjectController();
$data = $controller->index();
$projects = $data['projects'];

// Count metrics
$activeProjects = count(array_filter($projects, fn($p) => $p->isActive()));
$totalProjects = count($projects);

ob_start();
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>Task Manager Dashboard</h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="/admin/task-manager/projects" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Project
            </a>
        </div>
    </div>

    <!-- Statistics Row -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Active Projects</h6>
                    <h2><?php echo $activeProjects; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Total Projects</h6>
                    <h2><?php echo $totalProjects; ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Projects Table -->
    <div class="card">
        <div class="card-header">
            <h5>Projects</h5>
        </div>
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Days Left</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                    <tr>
                        <td>
                            <a href="/admin/task-manager/project/<?php echo $project->id; ?>">
                                <?php echo htmlspecialchars($project->name); ?>
                            </a>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $project->status === 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($project->status); ?>
                            </span>
                        </td>
                        <td><?php echo $project->start_date ?? 'N/A'; ?></td>
                        <td><?php echo $project->end_date ?? 'N/A'; ?></td>
                        <td>
                            <?php 
                            $daysLeft = $project->getDaysRemaining();
                            echo $daysLeft !== null ? $daysLeft . ' days' : 'N/A';
                            ?>
                        </td>
                        <td>
                            <a href="/admin/task-manager/project/<?php echo $project->id; ?>" class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="/admin/task-manager/project/<?php echo $project->id; ?>/edit" class="btn btn-sm btn-warning">
                                <i class="fas fa-edit"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require $_SERVER['DOCUMENT_ROOT'] . "/app/templates/new_base.php";
?>
```

## Installation Steps

1. **Create Database Tables**:
   ```bash
   mysql -u root -p YOUR_DB_NAME < sql/project_task_manager.sql
   ```

2. **Create Directory Structure**:
   ```bash
   mkdir -p app/internal/admin/task-manager/{controllers,views/{projects,tasks,time-entries,reports}}
   mkdir -p app/logic/task-manager
   ```

3. **Add Models** (copy code above):
   - `app/models/Project.php`
   - `app/models/Task.php`
   - `app/models/TimeEntry.php`

4. **Add Repositories** (copy code above):
   - `app/repository/ProjectRepository.php`
   - `app/repository/TaskRepository.php`
   - `app/repository/TimeEntryRepository.php`

5. **Add Controllers**:
   - `app/internal/admin/controllers/ProjectController.php`
   - `app/internal/admin/controllers/TaskController.php`
   - `app/internal/admin/controllers/TimeEntryController.php`

6. **Add Routes** to `routes.php`

7. **Create Views** in `app/internal/admin/task-manager/`

8. **Create Logic Files** in `app/logic/task-manager/`

## Access Control

Ensure admin authentication middleware is in place:

```php
// At start of task-manager views/logic files
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('HTTP/1.0 403 Forbidden');
    die('Access Denied: Admin only');
}
```

## JavaScript Time Logging

```javascript
// For time entry form (start/end time calculation)
document.addEventListener('DOMContentLoaded', function() {
    const startTimeInput = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');
    const durationInput = document.getElementById('duration_hours');

    function calculateDuration() {
        if (startTimeInput.value && endTimeInput.value) {
            const start = new Date(`2000-01-01 ${startTimeInput.value}`);
            const end = new Date(`2000-01-01 ${endTimeInput.value}`);
            const diff = (end - start) / (1000 * 60 * 60);
            durationInput.value = diff.toFixed(2);
        }
    }

    startTimeInput?.addEventListener('change', calculateDuration);
    endTimeInput?.addEventListener('change', calculateDuration);
});
```

## Summary

This implementation provides:

✅ Complete project lifecycle management  
✅ Task organization with priorities and status  
✅ Detailed time tracking with flexible logging  
✅ Time-based analytics and reporting  
✅ Admin-only access control  
✅ Full audit trail via timestamps  
✅ RESTful API endpoints for UI interactions  
✅ Scalable repository pattern architecture  

All functionality is integrated into the existing admin panel and follows current Novocib patterns.
