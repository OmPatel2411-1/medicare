import mysql.connector
import pandas as pd
import matplotlib.pyplot as plt
import seaborn as sns
import os
from datetime import datetime, timedelta
import sys

# Database connection
try:
    conn = mysql.connector.connect(
        host="localhost",
        user="root",
        password="",
        database="medicalportal"
    )
except mysql.connector.Error as e:
    print(f"Database connection failed: {e}")
    sys.exit(1)

# Ensure the graphs directory exists
graphs_dir = "graphs"
if not os.path.exists(graphs_dir):
    os.makedirs(graphs_dir)

# Get the patient ID from command-line argument (passed from PHP)
if len(sys.argv) != 2:
    print("Please provide patient_id as an argument")
    sys.exit(1)
patient_id = int(sys.argv[1])

# Check if the patient exists
query = "SELECT * FROM patients WHERE id = %s"
cursor = conn.cursor()
cursor.execute(query, (patient_id,))
if not cursor.fetchone():
    print(f"Patient with ID {patient_id} does not exist")
    sys.exit(1)
cursor.close()

# 1. Appointments Over the Past 6 Months (Bar Graph)
end_date = datetime.now()
start_date = end_date - timedelta(days=180)  # 6 months ago

query = """
    SELECT DATE_FORMAT(date, '%Y-%m') AS month, COUNT(*) AS count
    FROM appointments
    WHERE patient_id = %s AND date BETWEEN %s AND %s
    GROUP BY month
    ORDER BY month
"""
df = pd.read_sql(query, conn, params=(patient_id, start_date, end_date))

# Create a list of the last 6 months for the x-axis
months = [(end_date - timedelta(days=30 * i)).strftime('%Y-%m') for i in range(5, -1, -1)]
counts = [0] * 6
for _, row in df.iterrows():
    month = row['month']
    if month in months:
        counts[months.index(month)] = row['count']

plt.figure(figsize=(10, 6))
sns.barplot(x=counts, y=months, palette="Blues_d")
plt.title("Appointments Over the Past 6 Months")
plt.xlabel("Number of Appointments")
plt.ylabel("Month")
plt.tight_layout()
plt.savefig(os.path.join(graphs_dir, f"appointments_{patient_id}.png"))
plt.close()

# 2. Blood Pressure Trends (Line Graph)
query = """
    SELECT date, blood_pressure
    FROM vitals
    WHERE user_id = (SELECT user_id FROM patients WHERE id = %s)
    ORDER BY date
    LIMIT 10
"""
df = pd.read_sql(query, conn, params=(patient_id,))
if not df.empty:
    df['systolic_bp'] = df['blood_pressure'].apply(lambda x: int(x.split('/')[0]))
    plt.figure(figsize=(10, 6))
    sns.lineplot(x='date', y='systolic_bp', data=df, marker='o', color='blue')
    plt.title("Blood Pressure Trends (Systolic)")
    plt.xlabel("Date")
    plt.ylabel("Systolic BP (mmHg)")
    plt.xticks(rotation=45)
    plt.tight_layout()
    plt.savefig(os.path.join(graphs_dir, f"blood_pressure_{patient_id}.png"))
    plt.close()
else:
    print(f"No blood pressure data for patient_id {patient_id}")

# 3. Sugar Level Trends (Line Graph)
query = """
    SELECT date, sugar_level
    FROM vitals
    WHERE user_id = (SELECT user_id FROM patients WHERE id = %s)
    ORDER BY date
    LIMIT 10
"""
df = pd.read_sql(query, conn, params=(patient_id,))
if not df.empty:
    plt.figure(figsize=(10, 6))
    sns.lineplot(x='date', y='sugar_level', data=df, marker='o', color='green')
    plt.title("Sugar Level Trends")
    plt.xlabel("Date")
    plt.ylabel("Sugar Level (mg/dL)")
    plt.xticks(rotation=45)
    plt.tight_layout()
    plt.savefig(os.path.join(graphs_dir, f"sugar_level_{patient_id}.png"))
    plt.close()
else:
    print(f"No sugar level data for patient_id {patient_id}")

# 4. Appointment Status Breakdown (Pie Chart)
query = """
    SELECT status, COUNT(*) AS count
    FROM appointments
    WHERE patient_id = %s
    GROUP BY status
"""
df = pd.read_sql(query, conn, params=(patient_id,))
if not df.empty:
    plt.figure(figsize=(8, 8))
    plt.pie(df['count'], labels=df['status'], autopct='%1.1f%%', colors=sns.color_palette("Set2"))
    plt.title("Appointment Status Breakdown")
    plt.tight_layout()
    plt.savefig(os.path.join(graphs_dir, f"appointment_status_{patient_id}.png"))
    plt.close()
else:
    print(f"No appointment data for patient_id {patient_id}")

# Close the database connection
conn.close()