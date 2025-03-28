import webbrowser
import tkinter as tk
import os

def open_php():
    file_path = os.path.abspath("signup.php")  # Get full path of signup.php
    webbrowser.open("file://" + file_path)  # Open in default browser

# Create main window
root = tk.Tk()
root.title("Open signup.php")

# Create button
button = tk.Button(root, text="Open signup.php", command=open_php, padx=20, pady=10)
button.pack(pady=20)

# Run the application
root.mainloop()
