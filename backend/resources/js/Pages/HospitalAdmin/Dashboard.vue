<template>
  <HospitalAdminLayout>
    <div class="hospital-admin-dashboard">
      <div class="header">
        <h1>Hospital Admin Dashboard</h1>
      </div>

      <div class="dashboard-cards">
        <div class="card">
          <div class="card-icon departments">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
              <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
          </div>
          <div class="card-content">
            <h3>Departments</h3>
            <p>Manage hospital departments and units</p>
            <a :href="route('hospital-admin.departments')" class="card-link">Manage →</a>
          </div>
        </div>

        <div class="card">
          <div class="card-icon staff">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="9" cy="7" r="4"></circle>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
          </div>
          <div class="card-content">
            <h3>Staff</h3>
            <p>Manage staff accounts and roles</p>
            <a :href="route('hospital-admin.staff')" class="card-link">Manage →</a>
          </div>
        </div>

        <div class="card">
          <div class="card-icon workflows">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="2" x2="12" y2="6"></line>
              <line x1="12" y1="18" x2="12" y2="22"></line>
              <line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line>
              <line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line>
              <line x1="2" y1="12" x2="6" y2="12"></line>
              <line x1="18" y1="12" x2="22" y2="12"></line>
              <line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line>
              <line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line>
            </svg>
          </div>
          <div class="card-content">
            <h3>Workflow Templates</h3>
            <p>Configure patient journey workflows</p>
            <a :href="route('hospital-admin.workflow-templates')" class="card-link">Manage →</a>
          </div>
        </div>

        <div class="card">
          <div class="card-icon reports">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="20" x2="18" y2="10"></line>
              <line x1="12" y1="20" x2="12" y2="4"></line>
              <line x1="6" y1="20" x2="6" y2="14"></line>
            </svg>
          </div>
          <div class="card-content">
            <h3>Reports</h3>
            <p>View department and financial reports</p>
            <a :href="route('hospital-admin.reports.department')" class="card-link">View →</a>
          </div>
        </div>

        <div class="card">
          <div class="card-icon audit">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="16" y1="13" x2="8" y2="13"></line>
              <line x1="16" y1="17" x2="8" y2="17"></line>
              <polyline points="10 9 9 9 8 9"></polyline>
            </svg>
          </div>
          <div class="card-content">
            <h3>Audit Logs</h3>
            <p>Review system activity and changes</p>
            <a :href="route('hospital-admin.audit-logs')" class="card-link">View →</a>
          </div>
        </div>
      </div>
    </div>
  </HospitalAdminLayout>
</template>

<script setup>
import HospitalAdminLayout from '../../Layouts/HospitalAdminLayout.vue';

const route = (name) => {
  const routes = {
    'hospital-admin.departments': '/hospital-admin/departments',
    'hospital-admin.staff': '/hospital-admin/staff',
    'hospital-admin.workflow-templates': '/hospital-admin/workflow-templates',
    'hospital-admin.reports.department': '/hospital-admin/reports/department-activity',
    'hospital-admin.audit-logs': '/hospital-admin/audit-logs',
  };
  return routes[name] || '/';
};
</script>

<style scoped>
.hospital-admin-dashboard {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

.header {
  margin-bottom: 2rem;
}

h1 {
  font-size: 2rem;
  color: #2c3e50;
  margin: 0;
}

.dashboard-cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.5rem;
}

.card {
  background: white;
  border-radius: 12px;
  padding: 2rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  transition: all 0.3s;
  display: flex;
  gap: 1.5rem;
}

.card:hover {
  transform: translateY(-4px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.card-icon {
  width: 64px;
  height: 64px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.card-icon.departments {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.card-icon.staff {
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
  color: white;
}

.card-icon.workflows {
  background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
  color: white;
}

.card-icon.reports {
  background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
  color: white;
}

.card-icon.audit {
  background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
  color: white;
}

.card-content {
  flex: 1;
}

.card-content h3 {
  margin: 0 0 0.5rem 0;
  font-size: 1.25rem;
  color: #2c3e50;
}

.card-content p {
  margin: 0 0 1rem 0;
  color: #6c757d;
  font-size: 0.9375rem;
}

.card-link {
  color: #007bff;
  text-decoration: none;
  font-weight: 600;
  font-size: 0.9375rem;
  transition: color 0.2s;
}

.card-link:hover {
  color: #0056b3;
}
</style>
