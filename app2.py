import pygame
import sys
import random

# Initialize Pygame
pygame.init()

# Set screen dimensions
WIDTH, HEIGHT = 800, 600
screen = pygame.display.set_mode((WIDTH, HEIGHT))
pygame.display.set_caption("Bouncing Balls with Mouse Interaction")

# Colors
WHITE = (255, 255, 255)
COLORS = [(255, 0, 0), (0, 255, 0), (0, 0, 255), (255, 255, 0), (255, 165, 0), (128, 0, 128)]
background_color = WHITE

# Ball class
class Ball:
    def __init__(self, x, y, radius, color):
        self.x = x
        self.y = y
        self.radius = radius
        self.color = color
        self.speed_x = random.uniform(-4, 4)
        self.speed_y = random.uniform(-4, 4)
        self.elasticity = 0.8  # Energy retained on bounce
        self.grabbed = False  # Is the ball grabbed by the mouse

    def update(self):
        if not self.grabbed:
            self.x += self.speed_x
            self.y += self.speed_y

            # Bounce off walls
            if self.x - self.radius <= 0 or self.x + self.radius >= WIDTH:
                self.speed_x = -self.speed_x * self.elasticity
                self.x = max(self.radius, min(WIDTH - self.radius, self.x))

            if self.y - self.radius <= 0 or self.y + self.radius >= HEIGHT:
                self.speed_y = -self.speed_y * self.elasticity
                self.y = max(self.radius, min(HEIGHT - self.radius, self.y))

    def draw(self, screen):
        pygame.draw.circle(screen, self.color, (int(self.x), int(self.y)), self.radius)

# Create multiple balls
balls = [
    Ball(random.randint(50, WIDTH - 50), random.randint(50, HEIGHT - 50), random.randint(20, 50), random.choice(COLORS))
    for _ in range(6)
]

# Frame rate
clock = pygame.time.Clock()
FPS = 60

# Game loop
running = True
dragged_ball = None
while running:
    screen.fill(background_color)

    # Handle events
    for event in pygame.event.get():
        if event.type == pygame.QUIT:
            running = False
        
        # Change background color on spacebar press
        if event.type == pygame.KEYDOWN and event.key == pygame.K_SPACE:
            background_color = random.choice(COLORS)

        # Mouse events for grabbing and throwing balls
        if event.type == pygame.MOUSEBUTTONDOWN:
            mouse_x, mouse_y = pygame.mouse.get_pos()
            for ball in balls:
                if (mouse_x - ball.x) ** 2 + (mouse_y - ball.y) ** 2 <= ball.radius ** 2:
                    ball.grabbed = True
                    dragged_ball = ball
                    pygame.event.set_grab(True)  # Lock cursor for smoother grabbing

        if event.type == pygame.MOUSEBUTTONUP:
            if dragged_ball:
                dragged_ball.grabbed = False
                mouse_speed_x, mouse_speed_y = pygame.mouse.get_rel()
                dragged_ball.speed_x = mouse_speed_x * 0.3  # Apply mouse velocity
                dragged_ball.speed_y = mouse_speed_y * 0.3
                dragged_ball = None
                pygame.event.set_grab(False)

    # Update balls
    for ball in balls:
        ball.update()

        # Move ball with mouse if grabbed
        if ball.grabbed:
            ball.x, ball.y = pygame.mouse.get_pos()

        ball.draw(screen)

    # Refresh screen
    pygame.display.flip()
    clock.tick(FPS)

pygame.quit()
sys.exit()